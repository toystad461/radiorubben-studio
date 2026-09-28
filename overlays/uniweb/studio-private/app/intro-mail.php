<?php
declare(strict_types=1);

/** A short, plain-text introduction. Credentials never enter the message. */
function studio_intro_message(array $record, array $config): array
{
    $name = trim((string)($record['name'] ?? ''));
    $role = studio_roles()[$record['role'] ?? ''] ?? 'Medarbeider';
    $sendingStep = ($record['role'] ?? '') === 'observer'
        ? '5. I Sending kan du lese sendelisten. Be en produsent eller programleder om endringer.'
        : '5. I Sending skriver du manus, kontrollerer innholdet og merker punkter klare. Ingenting går automatisk på lufta.';
    $base = rtrim((string)($config['base_url'] ?? ''), '/');
    if ($name === '' || preg_match('/[\x00-\x1f\x7f]/', $name)
        || !studio_local_valid_email((string)($record['email'] ?? ''))
        || !str_starts_with($base, 'https://')) throw new InvalidArgumentException('Ugyldig mottaker eller Studio-adresse.');

    $body = "Hei {$name}!\n\n"
        . "Velkommen som medarbeider i Radio Rubben Studio. Kontoen din er opprettet med rollen {$role}.\n\n"
        . "Kom i gang:\n"
        . "1. Åpne {$base}/local/login.php og logg inn med e-postadressen din.\n"
        . "2. Få det midlertidige passordet fra administrator gjennom en sikker kanal. Du velger et nytt passord ved første innlogging.\n"
        . "3. Start i Kontrollsenter. Der ser du sendelisten og snarveier til dagens arbeid.\n"
        . "4. I Nyhetsdesk finner du lokalsaker, vær og vegmeldinger. Kontroller alltid originalkilden.\n"
        . $sendingStep . "\n\n"
        . "Manus og stikk som skal arkiveres i OneDrive, legger vi i Radio Rubbens arbeidsområde Manus & Stikk etter gjeldende rutine. "
        . "Spør administrator om tilgang til OneDrive-mappen og hvilke verktøy rollen din omfatter.\n\n"
        . "Trenger du hjelp, kontakt administrator i Radio Rubben.\n\n"
        . "Hilsen\nRadio Rubben Studio\n";
    return ['subject'=>'Velkommen til Radio Rubben Studio', 'body'=>$body];
}

/** True means the mail transport accepted the message, not confirmed inbox delivery. */
function studio_intro_send(array $record, array $config, ?callable $transport = null): bool
{
    $from = strtolower(trim((string)($config['intro_from_email'] ?? '')));
    if (!studio_intro_sender_ready($config)) {
        throw new RuntimeException('Intro-e-post krever en konfigurert avsender på radiorubben.no.');
    }
    $message = studio_intro_message($record, $config);
    $headers = [
        'From' => 'Radio Rubben Studio <' . $from . '>',
        'Reply-To' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ];
    $subject = '=?UTF-8?B?' . base64_encode($message['subject']) . '?=';
    $transport ??= static fn(string $to, string $subject, string $body, array $headers): bool => mail($to, $subject, $body, $headers);
    return $transport($record['email'], $subject, $message['body'], $headers) === true;
}

function studio_intro_sender_ready(array $config): bool
{
    $from = strtolower(trim((string)($config['intro_from_email'] ?? '')));
    return studio_local_valid_email($from) && str_ends_with($from, '@radiorubben.no');
}
