Ciao {{ $fullName }},

ti informiamo che l'utente {{ $invitedFullName }} ha cercato di attivare
il suo invito al portale [{{ config('app.name') }}]({{ url('/') }}),
ma il link è scaduto.

Se lo desideri, è possibile inviarne uno nuovo dal suo [profilo]({{ $profileUrl }}).