<?php

/**
 * This file is part of Zwii.
 *
 * For full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 *
 * @author Rémi Jean <remi.jean@outlook.com>
 * @copyright Copyright (C) 2008-2018, Rémi Jean
 * @author Frédéric Tempez <frederic.tempez@outlook.com>
 * @copyright Copyright (C) 2018-2020, Frédéric Tempez
 * @copyright Sylvain Lelièvre
 * @license GNU General Public License, version 3
 * @link http://zwiicms.com/
 *
 */

class search extends common {

	public static $actions = [
		'index' => self::GROUP_VISITOR,
		'config' => self::GROUP_MODERATOR
	];

	// Variable pour l'affichage des résultats
	public static $resultList = '';
	public static $resultError = '';
	public static $resultTitle = '';

	public static $nbResults = 0;
	public static $motclef = '';
	public static $motentier = '';

	const SEARCH_VERSION = '1.1';

	// Configuration vide
	public function config() {
		// Initialisation des données de thème de la galerie dasn theme.json
		// Création des valeur par défaut absentes
		if ( $this->getData(['theme', 'search']) === null ) {
			require_once('module/search/ressource/defaultdata.php');
			$this->setData(['theme', 'search', theme::$defaultData]);
		}
		if($this->isPost())  {
			// Soumission du formulaire
			$this->setData(['theme', 'search', [
				'keywordColor' => $this->getInput('searchKeywordColor')
			]]);
			$this->setData(['module', $this->getUrl(0), [
				'submitText' => $this->getInput('searchSubmitText'),
				'placeHolder' => $this->getInput('searchPlaceHolder'),
				'resultHideContent' => $this->getInput('searchResultHideContent',helper::FILTER_BOOLEAN),
			]]);
			// Création des fichiers CSS
			$content = file_get_contents('module/search/ressource/vartheme.css');
			$themeCss = file_get_contents('module/search/ressource/theme.css');
			// Injection des variables
			$content = str_replace('#keywordColor#',$this->getinput('searchKeywordColor'),$content );
			$success = file_put_contents('module/search/view/index/index.css',$content . $themeCss);

			// Valeurs en sortie, affichage du formulaire
			$this->addOutput([
				'redirect' => helper::baseUrl() . $this->getUrl(),
				'notification' => $success !== FALSE ? 'Modifications enregistrées' : 'Modifications non enregistées !',
				'state' => $success !== FALSE
			]);

		}
		// Valeurs en sortie, affichage du formulaire
		$this->addOutput([
			'title' => 'Configuration du module',
			'view' => 'config',
			'vendor' => [
				'tinycolorpicker'
			]
		]);
	}

	public function index() {
		if($this->isPost())  {
			//Initialisations variables
			$success = true;
			$result = [];
			$notification = '';
			$total='';
			self::$nbResults = 0;

			// Récupération du mot clef passé par le formulaire de ...view/index.php, avec caractères accentués
			self::$motclef=$this->getInput('searchMotphraseclef');

			// Récupération de l'état de l'option mot entier passé par le même formulaire
			self::$motentier=$this->getInput('searchMotentier', helper::FILTER_BOOLEAN);

			//Pour affichage de l'entête du résultat
			$keywords = '/(';
			$a = explode(' ',self::$motclef);
			foreach ($a as $key => $value) {

				$keywords .= self::$motentier === false ? $value . '|' : '\\b' . $value . '\\b|' ;
			}
			$keywords = substr($keywords,0,strlen($keywords) - 1);
			$keywords .= ')/i';
			$keywords = str_replace ('+', ' ',$keywords);
			if (self::$motclef !== '' ) {
				foreach($this->getHierarchy(null,false,null) as $parentId => $childIds) {
					if ($this->getData(['page', $parentId, 'disable']) === false  &&
                        $this->getUser('group') >= $this->getData(['page', $parentId, 'group']) &&
                        $this->getData(['page', $parentId, 'block']) !== 'bar') 	{
						$url = $parentId;
						$titre = $this->getData(['page', $parentId, 'title']);
						$contenu =  $this->getData(['page', $parentId, 'content']);
						// Pages sauf pages filles et articles de blog
						$tempData  = $this->occurrence($url, $titre, $contenu, $keywords, self::$motentier);
						if (is_array($tempData) ) {
							$result [] = $tempData;
						}
					}

					foreach($childIds as $childId) {
							// Sous page
							if ($this->getData(['page', $childId, 'disable']) === false &&
                                $this->getUser('group') >= $this->getData(['page', $parentId, 'group']) &&
                                $this->getData(['page', $parentId, 'block']) !== 'bar') 	{
                                    $url = $childId;
                                    $titre = $this->getData(['page', $childId, 'title']);
                                    $contenu = $this->getData(['page', $childId, 'content']);
                                    //Pages filles
									$tempData  = $this->occurrence($url, $titre, $contenu, $keywords, self::$motentier);
									if (is_array($tempData) ) {
										$result [] = $tempData;
									}
							}

							// Articles d'une sous-page blog
							if ($this->getData(['page', $childId, 'moduleId']) === 'blog')
							{
								foreach($this->getData(['module',$childId]) as $articleId => $article) {
									if($this->getData(['module',$childId,$articleId,'state']) === true)  {
										$url = $childId . '/' . $articleId;
										$titre = $article['title'];
										$contenu = $article['content'];
										// Articles de sous-page de type blog
										$tempData  = $this->occurrence($url, $titre, $contenu, $keywords, self::$motentier);
										if (is_array($tempData) ) {
											$result [] = $tempData;
										}
									}
                                }
							}
                    }

					// Articles d'un blog
					if ($this->getData(['page', $parentId, 'moduleId']) === 'blog' ) {
						foreach($this->getData(['module',$parentId]) as $articleId => $article) {
							if($this->getData(['module',$parentId,$articleId,'state']) === true)
							{
								$url = $parentId. '/' . $articleId;
								$titre = $article['title'];
								$contenu = $article['content'];
								// Articles de Blog
								$tempData  = $this->occurrence($url, $titre, $contenu, $keywords, self::$motentier);
								if (is_array($tempData) ) {
									$result [] = $tempData;
								}
							}
                        }
					}
				}

				// Message de synthèse de la recherche
				if (count($result) === 0) 	{
					self::$resultTitle = 'Aucun résultat';
					self::$resultError = 'Avez-vous pens&eacute; aux accents ?';
				} else  {
					self::$resultError = '';
					self::$resultTitle = ' Résultat de votre recherche';
					rsort($result);
					foreach ($result as $key => $value) {
						$r [] = $value['preview'];
					}
					// Générer une chaine de caractères
					self::$resultList= implode("", $r);
				}
			}

			// Valeurs en sortie, affichage du résultat
			$this->addOutput([
				'view' => 'index',
				'showBarEditButton' => true,
				'showPageContent' => !$this->getData(['module', $this->getUrl(0),'resultHideContent'])
			]);
		} else {
			// Valeurs en sortie, affichage du formulaire
			$this->addOutput([
				'view' => 'index',
				'showBarEditButton' => true,
				'showPageContent' => true
			]);
		}
	}


	// Fonction de recherche des occurrences dans $contenu
	// Renvoie le résultat sous forme de chaîne
	private function occurrence($url, $titre, $contenu, $motclef, $motentier)
	{
		// Nettoyage de $contenu : on enlève tout ce qui est inclus entre < et >
		$contenu = preg_replace ('/<[^>]*>/', ' ', $contenu);
		// Accentuation
		$contenu = html_entity_decode($contenu);
		// Initialisations

		$valid = preg_match_all($motclef,$contenu,$matches,PREG_OFFSET_CAPTURE);
		if ($valid > 0 ) {
			if (($matches[0][0][1]) > 0) {
				$resultat = '<h2><a  href="./?'.$url.'" target="_blank" rel="noopener">' . $titre .  '</a></h2>';
				// Création de l'aperçu
				// Eviter de découper avec une valeur négative
				$d = $matches[0][0][1] - 50 < 0 ? 1 : $matches[0][0][1] - 50;
				// Rechercher l'espace le plus proche
				$d = $d > 1 ? strpos($contenu,' ',$d) : $d;
				// Découper l'aperçu
				$t = substr($contenu,(int) $d ,300);
				// Applique une mise en évidence
				$t = preg_replace($motclef, '<span class="searchKeyword">\1</span>',$t);
				// Sauver résultat
				$resultat .= '<p class="searchResult">'.$t.'...</p>';
				$resultat .= '<p class="searchTitle">'.count($matches[0]) . (count($matches[0]) === 1 ? ' occurence<p>' : ' occurences<p>');
				//}
				return ([
					'matches' => count($matches[0]),
					'preview' => $resultat
				]);
			}
		}
	}
}
