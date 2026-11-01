# Template checked against Ubuntu Liquidsoap 2.2.4-1. PHP replaces JSON-quoted
# private values. No browser data, track titles or user-supplied code here.
settings.log.file := false
settings.log.stdout := false
settings.init.allow_root := {{ALLOW_ROOT}}
settings.server.telnet := false
settings.server.socket := false

connected = ref(false)
session = {{SESSION}}
event_clock = ref(fun () -> 0.)

def emit(event, entry) =
  now = event_clock()
  payload = json.stringify(compact=true, {event=event, entry=entry, clock=now(), session=session})
  print(newline=false, "RR_EVENT #{payload}\n")
end

def emit_track(event, metadata) =
  entry = metadata["rr_entry"]
  # fade.in can produce an internal zero-length track before real metadata.
  if entry != "" then emit(event, entry) end
end

def claim_next() =
  if not connected() then
    null()
  else
    command = process.quote.command(args=[{{RPC}}, "claim", {{ROOT}}, session], {{PHP}})
    response = process.run(timeout=5., command)
    uri = string.trim(response.stdout)
    if response.status.code != 0 then
      emit("claim_error", "")
      null()
    elsif uri == "" then
      null()
    else
      request.create(temporary=false, uri)
    end
  end
end

queue = request.dynamic(id="rubben_queue", prefetch=1, retry_delay=0.2, timeout=5., claim_next)
# End markers are released only as the final output PCM clock consumes the
# buffered outgoing tail. Decoder read-ahead itself is never a played receipt.
pending_ends = ref([])
def transition(a, b) =
  tail = source.duration(a.source)
  clock = event_clock()
  if a.metadata["rr_entry"] != "" then
    pending_ends := list.append(pending_ends(), [(a.metadata["rr_entry"], clock() + tail)])
  end
  incoming = source.on_track(b.source, fun (_) -> emit_track("started", b.metadata))
  if a.metadata["rr_kind"] == "music" and b.metadata["rr_kind"] == "music" and tail > 0. then
    add(normalize=false, [fade.in(type="lin", duration=tail, incoming), fade.out(type="lin", duration=tail, a.source)])
  else
    sequence([a.source, incoming])
  end
end
music = if {{ROTATION_MODE}} then
  cross(duration=3., override_duration="rr_cross", persist_override=false, transition, queue)
else
  # Preserve L01's existing contract for non-rotation queues.
  fade.in(duration=1., track_sensitive=true, fade.out(duration=1., track_sensitive=true, queue))
end
music = source.on_track(music, fun (m) -> emit_track("started", m))
music = source.on_metadata(music, fun (m) -> emit_track("started", m))
music = source.on_end(delay=-1., music, fun (_, m) -> emit_track("ended", m))
radio = fallback(track_sensitive=false, [music, blank()])
# This callback is pulled by the audio output clock, including during blank
# frames. A timer on a separate thread would not detect a stalled audio path.
event_clock := fun () -> radio.time()
last_heartbeat = ref(-1.)
def on_pcm_frame() =
  now = radio.time()
  due = list.filter(fun (item) -> snd(item) <= now, pending_ends())
  pending_ends := list.filter(fun (item) -> snd(item) > now, pending_ends())
  list.iter(fun (item) -> emit("ended", fst(item)), due)
  if now - last_heartbeat() >= 1. then
    last_heartbeat := now
    emit("heartbeat", "")
  end
end
radio = source.on_frame(radio, on_pcm_frame)

def on_connect() =
  connected := true
  emit("connected", "")
end

def on_disconnect() =
  connected := false
  emit("disconnected", "")
end

def on_error(_) =
  connected := false
  emit("output_error", "")
  1.
end

output.icecast(
  id="rubben_output", %mp3(bitrate=192, samplerate=44100, stereo=true),
  host="127.0.0.1", port={{PORT}}, password={{PASSWORD}},
  mount="/rubben-test.mp3", name="Radio Rubben PRIVATE TEST",
  public=false, fallible=true,
  on_connect=on_connect, on_disconnect=on_disconnect, on_error=on_error,
  radio
)
