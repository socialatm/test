
Dank je voor je aanmelding op {{$sitename}}.

Jouw inloggegevens zijn als volgt:

Hub:	{{$siteurl}}
Inlognaam:	{{$email}}

Log in met het wachtwoord die je tijdens het registreren hebt gekozen.

Wij dienen jouw e-mailadres te verifiëren om je volledig toegang te kunnen geven.

Jouw verificatie token is

{{$hash}}

{{if $timeframe}}
Dit token is geldig van {{$timeframe.0}} UTC tot {{$timeframe.1}} UTC

{{/if}}

Wanneer jij dit account hebt aangemaakt, vul dan de verificatie token in wanneer daarom wordt gevraagd of ga naar de volgende link:

{{$siteurl}}/regate/{{$mail}}


Om de registratie van dit account te annuleren en deze te verwijderen bezoek je:

{{$siteurl}}/regate/{{$mail}}{{if $ko}}/{{$ko}}{{/if}}


Bedankt
