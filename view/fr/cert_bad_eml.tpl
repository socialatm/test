Il s'agit du serveur web de {{$sitename}} ;

Une vérification de routine indique que le certificat SSL de ce site web n'est pas valide.

Votre site web ne peut pas fonctionner correctement avec Hubzilla tant que ce problème n'est pas résolu. Veuillez vérifier votre certificat et avec votre fournisseur de certificat ou votre fournisseur de service pour vous assurer qu'il est "valide pour le navigateur" et installé correctement. Les certificats auto-signés ne sont PAS SUPPORTÉS et NE SONT PAS AUTORISÉS dans Hubzilla. La vérification est effectuée en récupérant une URL de votre site web avec une vérification SSL stricte activée, et si cela échoue, une nouvelle vérification est effectuée avec des vérifications SSL désactivées. Il est possible qu'une erreur transitoire produise ce message, mais si des changements récents de configuration ont été effectués, ou si vous recevez ce message plus d'une fois, veuillez vérifier votre certificat. Le message d'erreur est "{{$error}}".

Veuillez nous excuser pour ce désagrément,

Votre serveur web à {{$siteurl}}
