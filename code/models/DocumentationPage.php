<?php

/**
 * A specific documentation page within a {@link DocumentationEntity}. 
 *
 * Maps to a file on the file system. Note that the URL to access this page may 
 * not always be the file name. If the file contains meta data with a nicer URL 
 * sthen it will use that. 
 * 
 * @package docsviewer
 * @subpackage model
 */
class DocumentationPage extends ViewableData {
	
	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var string
	 */
	protected $summary;

	/**
	 * @var DocumentationEntityLanguage
	 */
	protected $entity;

	/**
	 * @var string
	 */
	protected $path, $filename;

	/**
	 * @param DocumentationEntityLanguage $entity
	 * @param string $filename
	 * @param string $path
	 */
	public function __construct(DocumentationEntityLanguage $entity, $filename, $path) {
		$this->filename = $filename;
		$this->path = $path;
		$this->entity = $entity;
	}

	/**
	 * @return string
	 */
	public function getExtension() {
		return DocumentationHelper::get_extension($this->filename);
	}
	
	/**
	 * @param string - has to be plain text for open search compatibility.
	 *
	 * @return string
	 */
	public function getBreadcrumbTitle($divider = ' - ') {
		$pathParts = explode('/', $this->getRelativeLink());
		
		// add the module to the breadcrumb trail.
		array_unshift($pathParts, $this->entity->getTitle());
		
		$titleParts = array_map(array(
			'DocumentationHelper', 'clean_page_name'
		), $pathParts);
		
		return implode($divider, $titleParts + array($this->getTitle()));
	}
	
	/**
	 * @return DocumentationEntityLanguage
	 */
	public function getEntity() {
		return $this->entity;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		if($this->title) {
			return $this->title;
		}

		return DocumentationHelper::clean_page_name($this->filename);
	}
	
	/**
	 * @return string
	 */
	public function getSummary() {
		return $this->summary;
	}

	/**
	 * Return the raw markdown for a given documentation page. 
	 *
	 * @param boolean $removeMetaData
	 *
	 * @return string
	 */
	public function getMarkdown($removeMetaData = false) {
		try {
			if ($md = file_get_contents($this->path)) {
				if ($this->title != 'Index') {
					$this->populateMetaDataFromText($md, $removeMetaData);
				}
			
				return $md;
			}
		}
		catch(InvalidArgumentException $e) {

		}
		
		return false;
	}
	
	/**
	 * Parse a file and return the parsed HTML version.
	 *
	 * @param string $baselink 
	 *
	 * @return string
	 */
	public function getHTML() {
		return DocumentationParser::parse(
			$this, 
			$this->entity->Link()
		);
	}

	/**
	 * @return string
	 */
	public function getRelativeLink() {
		$path = str_replace($this->entity->getPath(), '', $this->path);
		$url = explode('/', $path);

		$url = implode('/', array_map(function($a) {
			return DocumentationHelper::clean_page_url($a);
		}, $url));

		$url = rtrim($url, '/') . '/';

		return $url;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Returns the URL that will be required for the user to hit to view the 
	 * given document base name.
	 *
	 * @param string $file
	 * @param string $path
	 *
	 * @return string
	 */
	public function Link() {
		return Controller::join_links(
			$this->entity->Link(),
			$this->getRelativeLink()
		);
	}
	
	/**
	 * Return metadata from the first html block in the page, then remove the 
	 * block on request
	 * 
	 * @param DocumentationPage $md
	 * @param bool $remove
	 */
	public function populateMetaDataFromText(&$md, $removeMetaData = false) {
		if($md) {
			// get the text up to the first whiteline
			$extPattern = "/^(.+)\n(\r)*\n/Uis";
			$matches = preg_match($extPattern, $md, $block);

			if($matches && $block[1]) {
				$metaDataFound = false;
				
				// find the key/value pairs
				$intPattern = '/(?<key>[A-Za-z][A-Za-z0-9_-]+)[\t]*:[\t]*(?<value>[^:\n\r\/]+)/x';
				$matches = preg_match_all($intPattern, $block[1], $meta);
				
				foreach($meta['key'] as $index => $key) {
					if(isset($meta['value'][$index])) {
						
						// check if a property exists for this key
						if (property_exists(get_class(), $key)) {
							$this->$key = $meta['value'][$index];

							$metaDataFound = true;
						}  
					}
				}

				// optionally remove the metadata block (only on the page that 
				// is displayed)
				if ($metaDataFound && $removeMetaData) {
					$md = preg_replace($extPattern, '', $md);
				}
			}
		}
	} 
}