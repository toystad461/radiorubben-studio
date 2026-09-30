export function validFader(config) {
  return config && typeof config.portId === 'string' && config.portId.length > 0
    && Number.isInteger(config.channel) && config.channel >= 1 && config.channel <= 16
    && Number.isInteger(config.number) && config.number >= 0 && config.number <= 127
    && Number.isInteger(config.down) && Number.isInteger(config.up)
    && config.down >= 0 && config.down <= 127 && config.up >= 0 && config.up <= 127 && config.down !== config.up;
}
export function faderLive(config, message, portId) {
  if (!validFader(config) || config.portId !== portId || message.type !== 'cc'
    || message.channel !== config.channel || message.number !== config.number) return null;
  return config.up > config.down ? message.value > config.down : message.value < config.down;
}
