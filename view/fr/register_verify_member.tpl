Merci de vous être enregistré sur {{$sitename}}.

Voici les détails de connexion :

Adresse du site: {{$siteurl}}
Utilisateur: {{$email}}

Connectez-vous avec le mot de passe que vous avez choisi au moment de l'enregistrement.

Nous devons vérifier votre adresse électronique afin de vous donner un accès complet au réseau. 

Votre code de vérification est :

{{$hash}}

{{if $timeframe}}
Ce code est valable de {{$timeframe.0}} UTC jusqu'à {{$timeframe.1}}

{{/if}}

Si vous avez enregistré ce compte, veuillez entrer le code de vérification lorsque cela vous est demandé ou cliquez sur le lien suivant :


{{$siteurl}}/regate/{{$mail}}

Pour refuser la demande et supprimer le compte, merci de vous rendre à cette adresse :
{{$siteurl}}/regate/{{$mail}}{{if $ko}}/{{$ko}}{{/if}}

Merci.
