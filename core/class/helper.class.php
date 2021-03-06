<?php
class helper {

	/** Statut de la réécriture d'URL (pour éviter de lire le contenu du fichier .htaccess à chaque self::baseUrl()) */
	public static $rewriteStatus = null;

	/** Filtres personnalisés */
	const FILTER_BOOLEAN = 1;
	const FILTER_DATETIME = 2;
	const FILTER_FLOAT = 3;
	const FILTER_ID = 4;
	const FILTER_INT = 5;
	const FILTER_MAIL = 6;
	const FILTER_PASSWORD = 7;
	const FILTER_STRING_LONG = 8;
	const FILTER_STRING_SHORT = 9;
	const FILTER_TIMESTAMP = 10;
	const FILTER_URL = 11;



	/** 
	 * Récupérer l'adresse IP sans tenit compte du proxy
	 * @return string IP adress
	 * Cette focntion est utilisé par user
	*/

	public static function getIp() {
		if(!empty($_SERVER['HTTP_CLIENT_IP'])){
			$ip=$_SERVER['HTTP_CLIENT_IP'];
		}
		elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
			$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else{
			$ip=$_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	/**
	 * Fonction pour récupérer le numéro de version en ligne
	 * @param string $url à récupérer
	 * @return mixed données récupérées
	 */

	public static function urlGetContents ($url) {
		// Ejecter free.fr
		if (strpos(self::baseUrl(),'free.fr') > 0 ){
			return false;
		}
		if(function_exists('file_get_contents') &&
				ini_get('allow_url_fopen') ){
				$url_get_contents_data = @file_get_contents($url); // Masque un warning éventuel
			}elseif(function_exists('fopen') &&
				function_exists('stream_get_contents') &&
				ini_get('allow_url_fopen')){
				$handle = fopen ($url, "r");
				$url_get_contents_data = stream_get_contents($handle);
			}else{
				$url_get_contents_data = false;
			}
		return $url_get_contents_data;
	}

	/**
	 * Retourne les valeurs d'une colonne du tableau de données
	 * @param array $array Tableau cible
	 * @param string $column Colonne à extraire
	 * @param string $sort Type de tri à appliquer au tableau (SORT_ASC, SORT_DESC, ou null)
	 * @return array
	 */
	public static function arrayCollumn($array, $column, $sort = null) {
		$newArray = [];
		if(empty($array) === false) {
			$newArray = array_map(function($element) use($column) {
				return $element[$column];
			}, $array);
			switch($sort) {
				case 'SORT_ASC':
					asort($newArray);
					break;
				case 'SORT_DESC':
					arsort($newArray);
					break;
			}
		}
		return $newArray;
	}


	/**
	 * Génère un backup des données de site
	 * @param string $folder dossier de sauvegarde
	 * @param array $exclude dossier exclus
	 * @return string nom du fichier de sauvegarde
	 */

	public static function autoBackup($folder, $filter = ['backup','tmp'] ) {
		// Creation du ZIP
		$baseName = str_replace('/','',helper::baseUrl(false,false));
		$baseName = empty($baseName) ? 'ZwiiCMS' : $baseName;
		$fileName =  $baseName . '-backup-' . date('Y-m-d-h-i-s', time()) . '.zip';
		$zip = new ZipArchive();
		$zip->open($folder . $fileName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$directory = 'site/';
		//$filter = array('backup','tmp','file');
		$files =  new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
			  new RecursiveDirectoryIterator(
				$directory,
				RecursiveDirectoryIterator::SKIP_DOTS
			  ),
			  function ($fileInfo, $key, $iterator) use ($filter) {
				return $fileInfo->isFile() || !in_array($fileInfo->getBaseName(), $filter);
			  }
			)
		  );
		foreach ($files as $name => $file) 	{
			if (!$file->isDir()) 	{
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen(realpath($directory)) + 1);
				$zip->addFile($filePath, $relativePath);
			}
		}
		$zip->close();
		return ($fileName);
	}

	/**
	 * Retourne true si le protocole est en TLS
	 * @return bool
	 */
	public static function isHttps() {
		if(
			(empty($_SERVER['HTTPS']) === false AND $_SERVER['HTTPS'] !== 'off')
			OR $_SERVER['SERVER_PORT'] === 443
		) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Retourne l'URL de base du site
	 * @param bool $queryString Affiche ou non le point d'interrogation
	 * @param bool $host Affiche ou non l'host
	 * @return string
	 */
	public static function baseUrl($queryString = true, $host = true) {
		// Protocole
		$protocol = helper::isHttps() === true ? 'https://' : 'http://';
		// Host
		if($host) {
			$host = $protocol . $_SERVER['HTTP_HOST'];
		}
		// Pathinfo
		$pathInfo = pathinfo($_SERVER['PHP_SELF']);
		// Querystring
		if($queryString AND helper::checkRewrite() === false) {
			$queryString = '?';
		}
		else {
			$queryString = '';
		}
		return $host . rtrim($pathInfo['dirname'], ' ' . DIRECTORY_SEPARATOR) . '/' . $queryString;
	}

	/**
	 * Check le statut de l'URL rewriting
	 * @return bool
	 */
	public static function checkRewrite() {
		if(self::$rewriteStatus === null) {
			// Ouvre et scinde le fichier .htaccess
			$htaccess = explode('# URL rewriting', file_get_contents('.htaccess'));
			// Retourne un boolean en fonction du contenu de la partie réservée à l'URL rewriting
			self::$rewriteStatus = (empty($htaccess[1]) === false);
		}
		return self::$rewriteStatus;
	}

	/**
	 * Renvoie le numéro de version de Zwii est en ligne
	 * @return string
	 */
	public static function getOnlineVersion() {
		return (helper::urlGetContents('http://zwiicms.fr/update/'. common::ZWII_UPDATE_CHANNEL . '/version'));
	}


	/**
	 * Check si une nouvelle version de Zwii est disponible
	 * @return bool
	 */
	public static function checkNewVersion() {

		if($version = helper::getOnlineVersion()) {
			//return (trim($version) !== common::ZWII_VERSION);
			return ((version_compare(common::ZWII_VERSION,$version)) === -1);
		}
		else {
			return false;
		}
	}


	/**
	 * Génère des variations d'une couleur
	 * @param string $rgba Code rgba de la couleur
	 * @return array
	 */
	public static function colorVariants($rgba) {
		preg_match('#\(+(.*)\)+#', $rgba, $matches);
		$rgba = explode(', ', $matches[1]);
		return [
			'normal' => 'rgba(' . $rgba[0] . ',' . $rgba[1] . ',' . $rgba[2] . ',' . $rgba[3] . ')',
			'darken' => 'rgba(' . max(0, $rgba[0] - 15) . ',' . max(0, $rgba[1] - 15) . ',' . max(0, $rgba[2] - 15) . ',' . $rgba[3] . ')',
			'veryDarken' => 'rgba(' . max(0, $rgba[0] - 20) . ',' . max(0, $rgba[1] - 20) . ',' . max(0, $rgba[2] - 20) . ',' . $rgba[3] . ')',
			'text' => self::relativeLuminanceW3C($rgba) > .22 ? "#222" : "#DDD"
		];
	}

	/**
	 * Supprime un cookie
	 * @param string $cookieKey Clé du cookie à supprimer
	 */
	public static function deleteCookie($cookieKey) {
		unset($_COOKIE[$cookieKey]);
		setcookie($cookieKey, '', time() - 3600, helper::baseUrl(false, false), '', false, true);
	}

	/**
	 * Filtre une chaîne en fonction d'un tableau de données
	 * @param string $text Chaîne à filtrer
	 * @param int $filter Type de filtre à appliquer
	 * @return string
	 */
	public static function filter($text, $filter) {
		$text = trim($text);
		switch($filter) {
			case self::FILTER_BOOLEAN:
				$text = (bool) $text;
				break;
			case self::FILTER_DATETIME:
				$timezone = new DateTimeZone(core::$timezone);
				$date = new DateTime($text);
				$date->setTimezone($timezone);
				$text = (int) $date->format('U');
				break;
			case self::FILTER_FLOAT:
				$text = filter_var($text, FILTER_SANITIZE_NUMBER_FLOAT);
				$text = (float) $text;
				break;
			case self::FILTER_ID:
				$text = mb_strtolower($text, 'UTF-8');
				$text = strip_tags(str_replace(
					explode(',', 'á,à,â,ä,ã,å,ç,é,è,ê,ë,í,ì,î,ï,ñ,ó,ò,ô,ö,õ,ú,ù,û,ü,ý,ÿ,\',", '),
					explode(',', 'a,a,a,a,a,a,c,e,e,e,e,i,i,i,i,n,o,o,o,o,o,u,u,u,u,y,y,-,-,-'),
					$text
				));
				$text = preg_replace('/([^a-z0-9-])/', '', $text);
				// Supprime les emoji
				$text = preg_replace('/[[:^print:]]/', '', $text);
				// Supprime les tirets en fin de chaine (emoji en fin de nom)
				$text = rtrim($text,'-');
				// Cas où un identifiant est vide
				if (empty($text)) {
					$text = uniqid('');
				}
				// Un ID ne peut pas être un entier, pour éviter les conflits avec le système de pagination
				if(intval($text) !== 0) {
					$text = 'i' . $text;
				}
				break;
			case self::FILTER_INT:
				$text = (int) filter_var($text, FILTER_SANITIZE_NUMBER_INT);
				break;
			case self::FILTER_MAIL:
				$text = filter_var($text, FILTER_SANITIZE_EMAIL);
				break;
			case self::FILTER_PASSWORD:
				$text = password_hash($text, PASSWORD_BCRYPT);
				break;
			case self::FILTER_STRING_LONG:
				$text = mb_substr(filter_var($text, FILTER_SANITIZE_STRING), 0, 500000);
				break;
			case self::FILTER_STRING_SHORT:
				$text = mb_substr(filter_var($text, FILTER_SANITIZE_STRING), 0, 500);
				break;
			case self::FILTER_TIMESTAMP:
				$text = date('Y-m-d H:i:s', $text);
				break;
			case self::FILTER_URL:
				$text = filter_var($text, FILTER_SANITIZE_URL);
				break;
		}
		return $text;
	}

	/**
	 * Incrémente une clé en fonction des clés ou des valeurs d'un tableau
	 * @param mixed $key Clé à incrémenter
	 * @param array $array Tableau à vérifier
	 * @return string
	 */
	public static function increment($key, $array = []) {
		// Pas besoin d'incrémenter si la clef n'existe pas
		if($array === []) {
			return $key;
		}
		// Incrémente la clef
		else {
			// Si la clef est numérique elle est incrémentée
			if(is_numeric($key)) {
				$newKey = $key;
				while(array_key_exists($newKey, $array) OR in_array($newKey, $array)) {
					$newKey++;
				}
			}
			// Sinon l'incrémentation est ajoutée après la clef
			else {
				$i = 2;
				$newKey = $key;
				while(array_key_exists($newKey, $array) OR in_array($newKey, $array)) {
					$newKey = $key . '-' . $i;
					$i++;
				}
			}
			return $newKey;
		}
	}

	/**
	 * Minimise du css
	 * @param string $css Css à minimiser
	 * @return string
	 */
	public static function minifyCss($css) {
		// Supprime les commentaires
		$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
		// Supprime les tabulations, espaces, nouvelles lignes, etc...
		$css = str_replace(["\r\n", "\r", "\n" ,"\t", '  ', '    ', '     '], '', $css);
		$css = preg_replace(['(( )+{)', '({( )+)'], '{', $css);
		$css = preg_replace(['(( )+})', '(}( )+)', '(;( )*})'], '}', $css);
		$css = preg_replace(['(;( )+)', '(( )+;)'], ';', $css);
		// Retourne le css minifié
		return $css;
	}

	/**
	 * Minimise du js
	 * @param string $js Js à minimiser
	 * @return string
	 */
	public static function minifyJs($js) {
		// Supprime les commentaires
		$js = preg_replace('/\\/\\*[^*]*\\*+([^\\/][^*]*\\*+)*\\/|\s*(?<![\:\=])\/\/.*/', '', $js);
		// Supprime les tabulations, espaces, nouvelles lignes, etc...
		$js = str_replace(["\r\n", "\r", "\t", "\n", '  ', '    ', '     '], '', $js);
		$js = preg_replace(['(( )+\))', '(\)( )+)'], ')', $js);
		// Retourne le js minifié
		return $js;
	}

	/**
	 * Crée un système de pagination (retourne un tableau contenant les informations sur la pagination (first, last, pages))
	 * @param array $array Tableau de donnée à utiliser
	 * @param string $url URL à utiliser, la dernière partie doit correspondre au numéro de page, par défaut utiliser $this->getUrl()
	 * @param string  $item pagination nombre d'éléments par page
	 * @param null|int $sufix Suffixe de l'url
	 * @return array
	 */
	public static function pagination($array, $url, $item, $sufix = null) {
		// Scinde l'url
		$url = explode('/', $url);
		// Url de pagination
		$urlPagination = is_numeric($url[count($url) - 1]) ? array_pop($url) : 1;
		// Url de la page courante
		$urlCurrent = implode('/', $url);
		// Nombre d'éléments à afficher
		$nbElements = count($array);
		// Nombre de page
		$nbPage = ceil($nbElements / $item);
		// Page courante
		$currentPage = is_numeric($urlPagination) ? self::filter($urlPagination, self::FILTER_INT) : 1;
		// Premier élément de la page
		$firstElement = ($currentPage - 1) * $item;
		// Dernier élément de la page
		$lastElement = $firstElement + $item;
		$lastElement = ($lastElement > $nbElements) ? $nbElements : $lastElement;
		// Mise en forme de la liste des pages
		$pages = '';
		if($nbPage > 1) {
			for($i = 1; $i <= $nbPage; $i++) {
				$disabled = ($i === $currentPage) ? ' class="disabled"' : false;
				$pages .= '<a href="' . helper::baseUrl() . $urlCurrent . '/' . $i . $sufix . '"' . $disabled . '>' . $i . '</a>';
			}
			$pages = '<div class="pagination">' . $pages . '</div>';
		}
		// Retourne un tableau contenant les informations sur la pagination
		return [
			'first' => $firstElement,
			'last' => $lastElement,
			'pages' => $pages
		];
	}

	/**
	 * Calcul de la luminance relative d'une couleur
	 */
	public static function relativeLuminanceW3C($rgba) {
		// Conversion en sRGB
		$RsRGB = $rgba[0] / 255;
		$GsRGB = $rgba[1] / 255;
		$BsRGB = $rgba[2] / 255;
		// Ajout de la transparence
		$RsRGBA = $rgba[3] * $RsRGB + (1 - $rgba[3]);
		$GsRGBA = $rgba[3] * $GsRGB + (1 - $rgba[3]);
		$BsRGBA = $rgba[3] * $BsRGB + (1 - $rgba[3]);
		// Calcul de la luminance
		$R = ($RsRGBA <= .03928) ? $RsRGBA / 12.92 : pow(($RsRGBA + .055) / 1.055, 2.4);
		$G = ($GsRGBA <= .03928) ? $GsRGBA / 12.92 : pow(($GsRGBA + .055) / 1.055, 2.4);
		$B = ($BsRGBA <= .03928) ? $BsRGBA / 12.92 : pow(($BsRGBA + .055) / 1.055, 2.4);
		return .2126 * $R + .7152 * $G + .0722 * $B;
	}

	/**
	 * Retourne les attributs d'une balise au bon format
	 * @param array $array Liste des attributs ($key => $value)
	 * @param array $exclude Clés à ignorer ($key)
	 * @return string
	 */
	public static function sprintAttributes(array $array = [], array $exclude = []) {
		$exclude = array_merge(
			[
				'before',
				'classWrapper',
				'help',
				'label'
			],
			$exclude
		);
		$attributes = [];
		foreach($array as $key => $value) {
			if(($value OR $value === 0) AND in_array($key, $exclude) === false) {
				// Désactive le message de modifications non enregistrées pour le champ
				if($key === 'noDirty') {
					$attributes[] = 'data-no-dirty';
				}
				// Disabled
				// Readonly
				elseif(in_array($key, ['disabled', 'readonly'])) {
					$attributes[] = sprintf('%s', $key);
				}
				// Autres
				else {
					$attributes[] = sprintf('%s="%s"', $key, $value);
				}
			}
		}
		return implode(' ', $attributes);
	}

	/**
	 * Retourne un segment de chaîne sans couper de mot
	 * @param string $text Texte à scinder
	 * @param int $start (voir substr de PHP pour fonctionnement)
	 * @param int $length (voir substr de PHP pour fonctionnement)
	 * @return string
	 */
	public static function subword($text, $start, $length) {
		$text = trim($text);
		if(strlen($text) > $length) {
			$text = mb_substr($text, $start, $length);
			$text = mb_substr($text, 0, min(mb_strlen($text), mb_strrpos($text, ' ')));
		}
		return $text;
	}

	/**
	 * Cryptage
	 * @param string $key la clé d'encryptage
	 * @param string $payload la chaine à coder
	 * @return string
	 */
	public static function encrypt($key, $payload) {
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
		$encrypted = openssl_encrypt($payload, 'aes-256-cbc', $key, 0, $iv);
		return base64_encode($encrypted . '::' . $iv);
	}

	/**
	 * Décryptage
	 * @param string $key la clé d'encryptage
	 * @param string $garble la chaine à décoder
	 * @return string
	 */
	public static  function decrypt($key, $garble) {
		list($encrypted_data, $iv) = explode('::', base64_decode($garble), 2);
		return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
	}

}