<?php

// Définissez les éléments suivants pour votre installation MySQL
// Utilisez ces informations pour configurer votre instance de BD (MySQL)
// Copier ou renomer ce fichier .htconfig.php et placer le à la racine de l'installation.

$db_host = '{{$dbhost}}';
$db_port = '{{$dbport}}';
$db_user = '{{$dbuser}}';
$db_pass = '{{$dbpass}}';
$db_data = '{{$dbdata}}';
$db_type = '{{$dbtype}}'; // an integer. 0 or unset for mysql, 1 for postgres

/*
 * Note: Plusieurs de ces réglages seront disponibles via le panneau d'administration
 * après l'installation. Lorsque des modifications sont apportés à travers le panneau d'administration
 * elle sont automatiquement enregistrées dans la base de données.
 * Les configurations inscrites dans la BD prévalent sur celles de ce fichier de configuration.
 *
 * En cas de difficultés d'accès au panneau d'administration, nous mettons à votre disposition,
 * un outil en ligne de commande est disponible [util/config] pour rechercher et apporter des modifications
 * sur les entrées dans la BD.
 *
 */

// Choisissez votre emplacement géographique. Si vous n'êtes pas certain, utilisez "America/Los_Angeles".
// Vous pourrez le changer plus tard et ce réglage n'affecte que les visiteurs anonymes.

App::$config['system']['timezone'] = '{{$timezone}}';

// Quels Sont l'url et le nom de votre site ? Ne pas mettre le slash à la fin

App::$config['system']['baseurl'] = '{{$siteurl}}';
App::$config['system']['sitename'] = "Hubzilla";
App::$config['system']['location_hash'] = '{{$site_id}}';

// Ces lignes définissent des en-têtes de sécurité supplémentaires à envoyer avec toutes les réponses.
// Vous pouvez définir transport_security_header à 0 si votre serveur envoie déjà cet en-tête.
// Content_security_policy peut être désactivé si vous souhaitez utiliser le plugin d'analyse Piwik ou inclure d'autres sites web.
// Utiliser le plugin piwik analytics ou ajouter d'autres ressources hors site sur une page.

App::$config['system']['transport_security_header'] = 1;
App::$config['system']['content_security_policy'] = 1;
App::$config['system']['ssl_cookie_protection'] = 1;

// Vos choix sont REGISTER_OPEN, REGISTER_APPROVE, ou REGISTER_CLOSED.
// Soyez certains de créer votre compte personnel avant de déclarer
// votre site REGISTER_CLOSED. 'register_text' (si vous décider de l'utiliser)
// renvois son contenu systématiquement sur la page d'enregistrement des nouveaux membres.
// REGISTER_APPROVE requiert la configuration de 'admin_email' avec l'adresse de courriel
// d'un membre déjà inscrit qui pourra autoriser et/ou approuver/supprimer la demande.

App::$config['system']['register_policy'] = REGISTER_OPEN;
App::$config['system']['register_text'] = '';
App::$config['system']['admin_email'] = '{{$adminmail}}';

// Il est recommandé de laisser cette valeur à 1. La valeur 0 permet aux personnes de s'inscrire sans avoir à prouver qu'elles possèdent une adresse électronique.
// vérifier que cette adresse électronique leur appartient.

App::$config['system']['verify_email'] = 1;

// Restrictions d'accès au site. Par défaut, nous allons créer des sites privés.
// Vous avez le choix entre ACCESS_PRIVATE, ACCESS_PAID, ACCESS_TIERED et ACCESS_FREE.
// Si vous laissez REGISTER_OPEN ci-dessus, n'importe qui peut s'inscrire sur votre site.
// Cependant, votre site ne sera pas répertorié comme un hub ouvert aux inscriptions.
// Nous utiliserons la politique d'accès au système (ci-dessous)
// pour déterminer s'il faut ou non inscrire votre site dans l'annuaire
// comme un hub ouvert où tout le monde peut créer des comptes. Vous avez le choix entre :
// inscription payante, à plusieurs niveaux ou gratuite : détermine la façon dont ces inscriptions seront présentées.

 App::$config['system']['access_policy'] = ACCESS_PRIVATE;

 // Si vous gérez un site public, vous souhaitez peut-être que les visiteurs soient dirigés // vers une "page d'accueil" où vous pouvez décrire en détail les caractéristiques, les politiques ou les services proposés.
 // Il doit s'agir d'une URL absolue commençant par http:// ou https:// .

 App::$config['system']['sellpage'] = '';

// taille maximale pour l'importation d'un message, 0 est illimité

App::$config['system']['max_import_size'] = 200000;

// taille maximale pour le téléversement de photos

App::$config['system']['maximagesize'] = 8000000;

// Lien absolu vers le compilateur PHP

App::$config['system']['php_path'] = '{{$phpath}}';

// configurez la façon dont votre site communique avec les autres serveurs. [Répertoire des membres inscrits à la Matrice]
// DIRECTORY_MODE_NORMAL     = client du répertoire de membres, nous vous trouverons un répertoire accessible autre serveur.
// DIRECTORY_MODE_SECONDARY  = copie mirroir du répertoire des membres.
// DIRECTORY_MODE_PRIMARY    = répertoire des membres principal.
// DIRECTORY_MODE_STANDALONE = "autonome/déconnecté" ou répertoire de membres privés

App::$config['system']['directory_mode']  = DIRECTORY_MODE_NORMAL;

// Thème par défaut

App::$config['system']['theme'] = 'redbasic';

// Configuration de l'enregistrement des erreurs PHP
// Avant de faire cela, assurez-vous que le serveur web a la permission de créer et d'écrire dans le fichier php.out dans le répertoire web correspondant.
// de créer et d'écrire dans le fichier php.out dans le répertoire web correspondant,
// ou changez le nom (ci-dessous) pour un fichier/chemin où cela est autorisé.
// Décommentez les 4 lignes suivantes pour activer la journalisation des erreurs PHP.

//error_reporting(E_ERROR | E_WARNING | E_PARSE ) ;
//ini_set('error_log', 'php.out') ;
//ini_set('log_errors', '1') ;
//ini_set('display_errors', '0') ;
