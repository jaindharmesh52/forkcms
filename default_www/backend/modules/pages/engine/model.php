<?php

/**
 * BackendPagesModel
 *
 * In this file we store all generic functions that we will be using in the PagesModule
 *
 *
 * @package		backend
 * @subpackage	pages
 *
 * @author 		Tijs Verkoyen <tijs@netlash.com>
 * @since		2.0
 */
class BackendPagesModel
{
	const QRY_BROWSE_RECENT = 'SELECT p.id, p.user_id, UNIX_TIMESTAMP(p.edited_on) AS edited_on, p.title
								FROM pages AS p
								WHERE p.status = ? AND p.language = ?
								ORDER BY p.edited_on DESC
								LIMIT ?';
	const QRY_BROWSE_REVISIONS = 'SELECT p.id, p.revision_id, p.user_id, UNIX_TIMESTAMP(p.edited_on) AS edited_on
									FROM pages AS p
									WHERE p.id = ? AND p.status = ? AND p.language = ?
									ORDER BY p.edited_on DESC;';


	/**
	 * Build the cache
	 *
	 * @return	void
	 */
	public static function buildCache()
	{
		// get tree
		$levels = self::getTree(array(0));

		// init vars
		$keys = array();
		$navigation = array();

		// loop levels
		foreach($levels as $level => $pages)
		{
			// loop all items on this level
			foreach($pages as $pageID => $page)
			{
				// init var
				$parentID = (int) $page['parent_id'];

				// get url for parent
				$url = (isset($keys[$parentID])) ? $keys[$parentID] : '';

				// home is special
				if($pageID == 1) $page['url'] = '';

				// add it
				$keys[$pageID] = trim($url .'/'. $page['url'], '/');

				// build navigation array
				$temp = array();
				$temp['page_id'] = $pageID;
				$temp['url'] = $page['url'];
				$temp['full_url'] = $keys[$pageID];
				$temp['title'] = $page['title'];
				$temp['navigation_title'] = $page['navigation_title'];
				$temp['has_extra'] = (bool) ($page['has_extra'] == 'Y');

				// calculate tree-type
				$treeType = 'page';
				if($page['hidden'] == 'Y') $treeType = 'hidden';

				// special items
				if($pageID == 1) $treeType = 'home';
				if($pageID == 2) $treeType = 'sitemap';
				if($pageID == 404) $treeType = 'error';

				// add type
				$temp['tree_type'] = $treeType;

				// add it
				$navigation[$page['type']][$page['parent_id']][$pageID] = $temp;
			}
		}

		// order by URL
		asort($keys);

		// write the key-file
		$keysString = '<?php' ."\n\n";
		$keysString .= '/**'."\n";
		$keysString .= ' * This file is generated by the Backend, it contains' ."\n";
		$keysString .= ' * the mapping between a pageID and the URL'."\n";
		$keysString .= ' * '."\n";
		$keysString .= ' * @author	Backend'."\n";
		$keysString .= ' * @generated	'. date('Y-m-d H:i:s') ."\n";
		$keysString .= ' */'."\n\n";
		$keysString .= '// init var'."\n";
		$keysString .= '$keys = array();'."\n\n";

		// loop all keys
		foreach($keys as $pageID => $url) $keysString .= '$keys['. $pageID .'] = \''. $url .'\';'."\n";

		// end file
		$keysString .= "\n".'?>';

		// write the file
		SpoonFile::setContent(PATH_WWW .'/frontend/cache/navigation/keys_'. BackendLanguage::getWorkingLanguage() .'.php', $keysString);

		// write the navigation-file
		$navigationString = '<?php' ."\n\n";
		$navigationString .= '/**'."\n";
		$navigationString .= ' * This file is generated by the Backend, it contains' ."\n";
		$navigationString .= ' * more information about the page-structure'."\n";
		$navigationString .= ' * '."\n";
		$navigationString .= ' * @author	Backend'."\n";
		$navigationString .= ' * @generated	'. date('Y-m-d H:i:s') ."\n";
		$navigationString .= ' */'."\n\n";
		$navigationString .= '// init var'."\n";
		$navigationString .= '$navigation = array();'."\n\n";

		// loop all types
		foreach($navigation as $type => $pages)
		{
			// loop all parents
			foreach($pages as $parentID => $page)
			{
				// loop all pages
				foreach($page as $pageID => $properties)
				{
					// loop properties
					foreach($properties as $key => $value)
					{
						// cast properly
						if($key == 'page_id') $value = (int) $value;
						elseif($key == 'has_extra') $value = ($value) ? 'true' : 'false';
						else $value = '\''. $value .'\'';

						// add line
						$navigationString .= '$navigation[\''. $type .'\']['. $parentID .']['. $pageID .'][\''. $key .'\'] = '. $value .';'."\n";
					}

					$navigationString .= "\n";
				}
			}
		}

		// end file
		$navigationString .= '?>';

		// write the file
		SpoonFile::setContent(PATH_WWW .'/frontend/cache/navigation/navigation_'. BackendLanguage::getWorkingLanguage() .'.php', $navigationString);
	}


	/**
	 * Build HTML for a template (visual representation)
	 *
	 * @return	string
	 * @param	array $template
	 */
	public static function buildTemplateHTML($template)
	{
		// validate
		if(!isset($template['data']['format'])) throw new BackendException('Invalid template-format.');

		// init var
		$html = '';

		// split into rows
		$rows = explode('],[', $template['data']['format']);

		// loop rows
		foreach($rows as $row)
		{
			// cleanup
			$row = str_replace(array('[',']'), '', $row);

			// add start html
			$html .= '<table border="0" cellpadding="2" cellspacing="2">'."\n";
			$html .= '	<tbody>'."\n";

			// split into cells
			$cells = explode(',', $row);

			// loop cells
			foreach($cells as $cell)
			{
				// selected state
				$selected = (substr_count($cell, ':selected') > 0);

				// remove selected state
				if($selected) $cell = str_replace(':selected', '', $cell);

				// decide selected state
				$exists = (isset($template['data']['names'][$cell]));

				// get title & index
				$title = ($exists) ? $template['data']['names'][$cell] : '';
				$index = ($exists) ? $cell : '';


				// does the cell need content
				if(!$exists) $html .= '		<td> </td>'."\n";

				else
				{
					// is the item selected?
					if($selected) $html .= '		<td class="selected"><a href="#block-'. $index .'" class="toggleDiv" title="'. $title .'">'. $index .'</a></td>'."\n";
					else $html .= '		<td><a href="#block-'. $index .'" class="toggleDiv" title="'. $title .'">'. $index .'</a></td>'."\n";
				}
			}

			// end html
			$html .= '	</tbody>'."\n";
			$html .= '</table>'."\n";
		}

		// return html
		return $html;
	}


	/**
	 * Creates the html for the menu
	 *
	 * @return	string
	 * @param	int[optional] $parentId
	 * @param	int[optional] $startDepth
	 * @param	int[optional] $maxDepth
	 * @param	array[optional] $excludedIds
	 * @param	string[optional] $html
	 */
	public static function createHtml($type = 'page', $depth = 0, $parentId = 1, $html = '')
	{
		// init var
		$navigation = array();

		// require
		require_once PATH_WWW .'/frontend/cache/navigation/navigation_'. BackendLanguage::getWorkingLanguage() .'.php';

		// check if item exists
		if(isset($navigation[$type][$depth][$parentId]))
		{
			// start html
			$html .= '<ul>' . "\n";

			// loop elements
			foreach($navigation[$type][$depth][$parentId] as $key => $aValue)
			{
				$html .= "\t<li>" . "\n";
				$html .= "\t\t". '<a href="#">'. $aValue['navigation_title'] .'</a>' . "\n";

				// insert recursive here!
				if(isset($navigation[$type][$depth + 1][$key])) $html .= self::createHtml($type, $depth + 1, $parentId, '');

				// add html
				$html .= '</li>' . "\n";
			}

			// end html
			$html .= '</ul>' . "\n";
		}

		// return
		return $html;
	}


	public static function delete($id, $language = null)
	{
		// redefine
		$id = (int) $id;
		$language = ($language === null) ? BackendLanguage::getWorkingLanguage() : (string) $language;

		// get db
		$db = BackendModel::getDB();

		// get record
		$page = self::get($id, $language);

		// validate
		if(empty($page)) return false;
		if($page['allow_delete'] == 'N') return false;

		// get revision ids
		$revisionIDs = (array) $db->getColumn('SELECT p.revision_id
												FROM pages AS p
												WHERE p.id = ? AND p.language = ?;',
												array($id, $language));

		// get meta ids
		$metaIDs = (array) $db->getColumn('SELECT p.meta_id
											FROM pages AS p
											WHERE p.id = ? AND p.language = ?;',
											array($id, $language));

		// delete meta records
		if(!empty($metaIDs)) $db->delete('meta', 'id IN ('. implode(',', $metaIDs) .')');

		// delete blocks and their revisions
		if(!empty($revisionIDs)) $db->delete('pages_blocks', 'revision_id IN ('. implode(',', $revisionIDs) .')');

		// delete page and the revisions
		if(!empty($revisionIDs)) $db->delete('pages', 'revision_id IN ('. implode(',', $revisionIDs) .')');

		// rebuild cach
		self::buildCache();

		// return
		return true;
	}


	/**
	 * Check if a page exists
	 *
	 * @return	bool
	 * @param	int $id
	 */
	public static function exists($id)
	{
		// redefine
		$id = (int) $id;
		$language = BackendLanguage::getWorkingLanguage();

		// get db
		$db = BackendModel::getDB();

		// get number of rows, if that result is more than 0 it means the page exists
		return (bool) ($db->getNumRows('SELECT p.id
										FROM pages AS p
										WHERE p.id = ? AND p.language = ? AND p.status IN ("active", "draft");',
										array($id, $language)) > 0);
	}


	public static function existsTemplate($id)
	{
		// get db
		$db = BackendModel::getDB();

		// get data
		return (bool) $db->getNumRows('SELECT id FROM pages_templates WHERE id = ?;', (int) $id);
	}


	/**
	 * Get the data for a record
	 *
	 * @return	array
	 * @param	int $id
	 */
	public static function get($id, $language = null)
	{
		// redefine
		$id = (int) $id;
		$language = ($language === null) ? BackendLanguage::getWorkingLanguage() : (string) $language;

		// get db
		$db = BackendModel::getDB();

		// get page (active version)
		$return = (array) $db->getRecord('SELECT *, UNIX_TIMESTAMP(p.publish_on) AS publish_on, UNIX_TIMESTAMP(p.created_on) AS created_on, UNIX_TIMESTAMP(p.edited_on) AS edited_on
											FROM pages AS p
											WHERE p.id = ? AND p.language = ? AND p.status = ?
											LIMIT 1;',
											array($id, $language, 'active'));

		// can't be deleted
		if(in_array($return['id'], array(1, 404))) $return['allow_delete'] = 'N';

		// can't be moved
		if(in_array($return['id'], array(1, 404))) $return['allow_move'] = 'N';

		// can't have children
		if(in_array($return['id'], array(404))) $return['allow_move'] = 'N';

		// convert into bools for use in template engine
		$return['move_allowed'] = (bool) ($return['allow_move'] == 'Y');
		$return['children_allowed'] = (bool) ($return['allow_children'] == 'Y');
		$return['edit_allowed'] = (bool) ($return['allow_edit'] == 'Y');
		$return['delete_allowed'] = (bool) ($return['allow_delete'] == 'Y');

		// return
		return $return;
	}


	public static function getTemplate($id)
	{
		// get db
		$db = BackendModel::getDB();

		// fetch data
		return (array) $db->getRecord('SELECT * FROM pages_templates WHERE id = ?;', (int) $id);
	}


	/**
	 * Get the revisioned data for a record
	 *
	 * @return	array
	 * @param	int $id
	 * @param	int $revisionId
	 */
	public static function getRevision($id, $revisionId)
	{
		// redefine
		$id = (int) $id;
		$revisionOd = (int) $revisionId;
		$language = BackendLanguage::getWorkingLanguage();

		// get db
		$db = BackendModel::getDB();

		$db->setDebug(true);

		// get page (active version)
		$return = (array) $db->getRecord('SELECT *, UNIX_TIMESTAMP(p.publish_on) AS publish_on, UNIX_TIMESTAMP(p.created_on) AS created_on, UNIX_TIMESTAMP(p.edited_on) AS edited_on
											FROM pages AS p
											WHERE p.id = ? AND p.revision_id = ? AND p.language = ?
											LIMIT 1;',
											array($id, $revisionId, $language));

		// can't be deleted
		if(in_array($return['id'], array(1, 404))) $return['allow_delete'] = 'N';

		// can't be moved
		if(in_array($return['id'], array(1, 404))) $return['allow_move'] = 'N';

		// can't have children
		if(in_array($return['id'], array(404))) $return['allow_move'] = 'N';

		// convert into bools for use in template engine
		$return['move_allowed'] = (bool) ($return['allow_move'] == 'Y');
		$return['children_allowed'] = (bool) ($return['allow_children'] == 'Y');
		$return['edit_allowed'] = (bool) ($return['allow_edit'] == 'Y');
		$return['delete_allowed'] = (bool) ($return['allow_delete'] == 'Y');

		// return
		return $return;
	}


	/**
	 * Get the blocks in a certain page
	 *
	 * @return	array
	 * @param	int $id
	 */
	public static function getBlocks($id)
	{
		// redefine
		$id = (int) $id;
		$language = BackendLanguage::getWorkingLanguage();

		// get db
		$db = BackendModel::getDB();

		// get page (active version)
		return (array) $db->retrieve('SELECT pb.*, UNIX_TIMESTAMP(pb.created_on) AS created_on, UNIX_TIMESTAMP(pb.edited_on) AS edited_on
										FROM pages_blocks AS pb
										INNER JOIN pages AS p ON pb.revision_id = p.revision_id
										WHERE p.id = ? AND p.language = ? AND p.status = ?;',
										array($id, $language, 'active'));
	}


	/**
	 * Get revisioned blocks for a certain page
	 *
	 * @return	array
	 * @param 	int $id
	 * @param	int $revisionId
	 */
	public static function getBlocksRevision($id, $revisionId)
	{
		// redefine
		$id = (int) $id;
		$revisionId = (int) $revisionId;
		$language = BackendLanguage::getWorkingLanguage();

		// get db
		$db = BackendModel::getDB();

		// get page (active version)
		return (array) $db->retrieve('SELECT pb.*, UNIX_TIMESTAMP(pb.created_on) AS created_on, UNIX_TIMESTAMP(pb.edited_on) AS edited_on
										FROM pages_blocks AS pb
										INNER JOIN pages AS p ON pb.revision_id = p.revision_id
										WHERE p.id = ? AND p.revision_id = ? AND p.language = ?;',
										array($id, $revisionId, $language));
	}


	/**
	 * Get all the available extra's
	 *
	 * @return	array
	 */
	public static function getExtrasData()
	{
		// get db
		$db = BackendModel::getDB();

		// get all extras
		$extras = (array) $db->retrieve('SELECT pe.id, pe.module, pe.type, pe.label, pe.data
											FROM pages_extras AS pe
											INNER JOIN modules AS m ON pe.module = m.name
											WHERE m.active = ?
											ORDER BY pe.module, pe.sequence;',
											array('Y'));

		// build array
		$values = array('dropdown' => array('' => array('html' => BL::getLabel('Editor'))));

		// loop extras
		foreach($extras as $row)
		{
			// unserialize data
			$row['data'] = @unserialize($row['data']);

			// set url if needed
			if(!isset($row['data']['url'])) $row['data']['url'] = BackendModel::createURLForAction('index', $row['module']);

			// build name
			$name = ucfirst(BL::getLabel($row['label']));
			if(isset($row['data']['extra_label'])) $name .= ' '. $row['data']['extra_label'];

			$moduleName = ucfirst(BL::getLabel(SpoonFilter::toCamelCase($row['module'])));

			// add
			$values['dropdown'][$moduleName][$row['id']] = $name;
			$values['types'][$row['id']] = $row['type'];
			$values['data'][$row['id']] = $row;
			$values['data'][$row['id']]['json'] = json_encode($row);
		}

		// return
		return $values;
	}


	/**
	 * Get the first child for a given parent
	 *
	 * @return	mixed
	 * @param	int $pageId
	 */
	public static function getFirstChildId($pageId)
	{
		// redefine
		$pageId = (int) $pageId;

		// get db
		$db = BackendModel::getDB();

		// get child
		$childId = (int) $db->getVar('SELECT p.id
										FROM pages AS p
										WHERE p.parent_id = ? AND p.status = ?
										ORDER BY p.sequence ASC
										LIMIT 1;',
										array($pageId, 'active'));

		if($childId != 0) return (int) $childId;

		// fallback
		return false;
	}

	/**
	 * Get the full-url for a given menuId
	 *
	 * @return	string
	 * @param	int $menuId
	 */
	public static function getFullURL($id)
	{
		// generate the cache files if needed
		if(!SpoonFile::exists(PATH_WWW .'/frontend/cache/navigation/keys_'. BackendLanguage::getWorkingLanguage() .'.php')) self::buildCache();

		// init var
		$keys = array();

		// require the file
		require PATH_WWW .'/frontend/cache/navigation/keys_'. BackendLanguage::getWorkingLanguage() .'.php';

		// available in generated file?
		if(isset($keys[$id])) $url = $keys[$id];

		// not availble
		else
		{
			// id 0 doesn't have an url
			if($id == 0) $url = '';
			else
			{
				// @todo	this method should use a genious caching-system
				throw new BackendException('You should implement me.');
			}
		}

		// if the is available in multiple languages we should add the current lang
		if(SITE_MULTILANGUAGE) $url = '/'. BackendLanguage::getWorkingLanguage() .'/'. $url;

		// just prepend with slash
		else $url = '/'. $url;

		// return
		return $url;
	}


	/**
	 * Get the maximum unique id for blocks
	 *
	 * @return	int
	 */
	public static function getMaximumBlockId()
	{
		// get db
		$db = BackendModel::getDB();

		// get the maximum id
		return (int) $db->getVar('SELECT MAX(pb.id)
									FROM pages_blocks AS pb;');
	}


	/**
	 * Get the maximum unique id for pages
	 *
	 * @return	int
	 * @param	string[optional] $language
	 */
	public static function getMaximumMenuId($language = null)
	{
		// redefine
		$language = ($language !== null) ? (string) $language : BackendLanguage::getWorkingLanguage();

		// get db
		$db = BackendModel::getDB();

		// get the maximum id
		$maximumMenuId = (int) $db->getVar('SELECT MAX(p.id)
											FROM pages AS p
											WHERE p.language = ?;',
											array($language));

		// pages created by a user should have an id higher then 1000
		// with this hack we can easily find pages added by a user
		if($maximumMenuId < 1000 && !BackendAuthentication::getUser()->isGod()) return $maximumMenuId + 1000;

		// fallback
		return $maximumMenuId;
	}


	/**
	 * Get the maximum sequence inside a leaf
	 *
	 * @return	int
	 * @param	int $parentId
	 * @param	int[optional] $language
	 */
	public static function getMaximumSequence($parentId, $language = null)
	{
		// redefine
		$parentId = (int) $parentId;
		$language = ($language !== null) ? (string) $language : BackendLanguage::getWorkingLanguage();

		// get db
		$db = BackendModel::getDB();

		// get the maximum sequence inside a certain leaf
		return (int) $db->getVar('SELECT MAX(p.sequence)
									FROM pages AS p
									WHERE p.language = ? AND p.parent_id = ?;',
									array($language, $parentId));
	}


	/**
	 * Get the subtree for a root element
	 *
	 * @return	string
	 * @param	array $navigation
	 * @param 	int $parentId
	 * @param	string[optional] $html
	 */
	public static function getSubtree($navigation, $parentId, $html = '')
	{
		// redefine
		$navigation = (array) $navigation;
		$parentId = (int) $parentId;
		$html = '';

		// any elements
		if(isset($navigation['page'][$parentId]) && !empty($navigation['page'][$parentId]))
		{
			// start
			$html .= '<ul>'."\n";

			// loop pages
			foreach($navigation['page'][$parentId] as $page)
			{
				// start
				$html .= '<li id="page-'. $page['page_id'] .'" rel="'. $page['tree_type'] .'">'."\n";

				// insert link
				$html .= '	<a href="'. BackendModel::createURLForAction('edit', null, null, array('id' => $page['page_id'])) .'"><ins>&#160;</ins>'. $page['navigation_title'] .'</a>'."\n";

				// get childs
				$html .= self::getSubtree($navigation, $page['page_id'], $html);

				// end
				$html .= '</li>'."\n";
			}

			// end
			$html .= '</ul>'."\n";
		}

		// return
		return $html;
	}


	/**
	 * Get templates
	 *
	 * @return unknown
	 */
	public static function getTemplates()
	{
		// get db
		$db = BackendModel::getDB();

		// get templates
		$templates = (array) $db->retrieve('SELECT t.id, t.label, t.path, t.num_blocks, t.is_default, t.data
											FROM pages_templates AS t
											WHERE t.active = ?;',
											array('Y'), 'id');

		// loop templates to unserialize the data
		foreach($templates as $key => $row)
		{
			// unserialize
			$templates[$key]['data'] = unserialize($row['data']);

			// build template HTML
			$templates[$key]['html'] = self::buildTemplateHTML($templates[$key]);
		}

		// add json
		foreach($templates as $key => $row)	$templates[$key]['json'] = json_encode($row);

		// return
		return (array) $templates;
	}


	/**
	 * Get all pages/level
	 *
	 * @param	array $ids
	 * @param	array[optional] $data
	 * @param	int[optional] $level
	 * @return	array
	 */
	private static function getTree(array $ids, array $data = null, $level = 1)
	{
		// get db
		$db = BackendModel::getDB();

		$data[$level] = (array) $db->retrieve('SELECT p.id, p.title, p.parent_id, p.navigation_title, p.type, p.hidden, p.has_extra,
													m.url
												FROM pages AS p
												INNER JOIN meta AS m ON p.meta_id = m.id
												WHERE p.parent_id IN ('. implode(', ', $ids) .')
												AND p.status = ? AND p.language = ?
												ORDER BY p.sequence ASC;',
												array('active', BackendLanguage::getWorkingLanguage()), 'id');

		// get the childIDs
		$childIds = array_keys($data[$level]);

		// build array
		if(!empty($data[$level])) $data = self::getTree($childIds, $data, ++$level);

		// cleanup
		else unset($data[$level]);

		return $data;
	}


	/**
	 * Get the tree
	 *
	 * @return	string
	 */
	public static function getTreeHTML()
	{
		// check if the cached file exists, if not we generated it
		if(!SpoonFile::exists(PATH_WWW .'/frontend/cache/navigation/navigation_'. BackendLanguage::getWorkingLanguage() .'.php')) self::buildCache();

		// init var
		$navigation = array();

		// require the file
		require_once PATH_WWW .'/frontend/cache/navigation/navigation_'. BackendLanguage::getWorkingLanguage() .'.php';

		// start HTML
		$html = '<h4>'. ucfirst(BL::getLabel('MainNavigation')) .'</h4>'."\n";
		$html .= '<div class="clearfix">'."\n";
		$html .= '	<ul>'."\n";
		$html .= '		<li id="page-1" rel="home">';

		// homepage should
		$html .= '			<a href="'. BackendModel::createURLForAction('edit', null, null, array('id' => 1)) .'"><ins>&#160;</ins>'. ucfirst(BL::getLabel('Home')) .'</a>'."\n";

		// add subpages
		$html .= self::getSubTree($navigation, 1);

		// end
		$html .= '		</li>'."\n";
		$html .= '	</ul>'."\n";
		$html .= '</div>'."\n";


		// are there any meta pages
		if(isset($navigation['meta'][0]) && !empty($navigation['meta'][0]))
		{
			// meta pages
			$html .= '<h4>'. ucfirst(BL::getLabel('Meta')) .'</h4>'."\n";

			$html .= '<div class="clearfix">'."\n";
			$html .= '	<ul>'."\n";

			// loop the items
			foreach($navigation['meta'][0] as $page)
			{
				// start
				$html .= '		<li id="page-'. $page['page_id'] .'" rel="'. $page['tree_type'] .'">'."\n";

				// insert link
				$html .= '			<a href="'. BackendModel::createURLForAction('edit', null, null, array('id' => $page['page_id'])) .'"><ins>&#160;</ins>'. $page['navigation_title'] .'</a>'."\n";

				// insert subtree
				$html .= self::getSubTree($navigation, $page['page_id']);

				// end
				$html .= '		</li>'."\n";
			}

			// end
			$html .= '	</ul>'."\n";
			$html .= '</div>'."\n";
		}

		// footer pages
		$html .= '<h4>'. ucfirst(BL::getLabel('Footer')) .'</h4>'."\n";

		// are there any footer pages
		if(isset($navigation['footer'][0]) && !empty($navigation['footer'][0]))
		{
			// start
			$html .= '<div class="clearfix">'."\n";
			$html .= '	<ul>'."\n";

			// loop the items
			foreach($navigation['footer'][0] as $page)
			{
				// start
				$html .= '		<li id="page-'. $page['page_id'] .'" rel="'. $page['tree_type'] .'">'."\n";

				// insert link
				$html .= '			<a href="'. BackendModel::createURLForAction('edit', null, null, array('id' => $page['page_id'])) .'"><ins>&#160;</ins>'. $page['navigation_title'] .'</a>'."\n";

				// end
				$html .= '		</li>'."\n";
			}

			// end
			// end
			$html .= '	</ul>'."\n";
			$html .= '</div>'."\n";
		}

		// are there any root pages
		if(isset($navigation['root'][0]) && !empty($navigation['root'][0]))
		{
			// meta pages
			$html .= '<h4>'. ucfirst(BL::getLabel('Root')) .'</h4>'."\n";

			// start
			$html .= '<div class="clearfix">'."\n";
			$html .= '	<ul>'."\n";

			// loop the items
			foreach($navigation['root'][0] as $page)
			{
				// start
				$html .= '		<li id="page-'. $page['page_id'] .'" rel="'. $page['tree_type'] .'">'."\n";

				// insert link
				$html .= '			<a href="'. BackendModel::createURLForAction('edit', null, null, array('id' => $page['page_id'])) .'"><ins>&#160;</ins>'. $page['navigation_title'] .'</a>'."\n";

				// insert subtree
				$html .= self::getSubTree($navigation, $page['page_id']);

				// end
				$html .= '		</li>'."\n";
			}

			// end
			$html .= '	</ul>'."\n";
			$html .= '</div>'."\n";
		}

		// return
		return $html;
	}


	/**
	 * Get an URL for a page
	 *
	 * @todo	urlise should be user in this function
	 *
	 * @return	string
	 * @param	string $url
	 * @param	int[optional] $id
	 * @param	int[optional] $parentId
	 */
	public static function getURL($url, $id = null, $parentId = 0)
	{
		// redefine
		$url = (string) $url;
		$parentId = (int) $parentId;

		// get db
		$db = BackendModel::getDB();

		// no specific id
		if($id === null)
		{
			// get number of childs within this parent with the specified url
			$number = (int) $db->getNumRows('SELECT p.id
												FROM pages AS p
												INNER JOIN meta AS m ON p.meta_id = m.id
												WHERE p.parent_id = ? AND  p.status = ? AND m.url = ?;',
												array($parentId, 'active', $url));

			// no items?
			if($number != 0)
			{
				// add a number
				$url = BackendModel::addNumber($url);

				// recall this method, but with a new url
				return self::getURL($url, null, $parentId);
			}
		}

		// one item should be ignored
		else
		{
			// get number of childs within this parent with the specified url
			$number = (int) $db->getNumRows('SELECT p.id
												FROM pages AS p
												INNER JOIN meta AS m ON p.meta_id = m.id
												WHERE p.parent_id = ? AND  p.status = ? AND m.url = ? AND p.id != ?;',
												array($parentId, 'active', $url, $id));

			// there are items so, call this method again.
			if($number != 0)
			{
				// add a number
				$url = self::addNumber($url);

				// recall this method, but with a new url
				return self::getURL($url, $id, $parentId);
			}
		}

		// get full url
		$fullUrl = self::getFullUrl($parentId) .'/'. $url;

		// check if folder exists
		if(SpoonDirectory::exists(PATH_WWW .'/'. $fullUrl))
		{
			// add a number
			$url = BackendModel::addNumber($url);

			// recall this method, but with a new url
			return self::getURL($url, $id, $parentId);
		}

		// check if it is an appliation
		if(in_array(trim($fullUrl, '/'), array_keys(ApplicationRouting::getRoutes())))
		{
			// add a number
			$url = BackendModel::addNumber($url);

			// recall this method, but with a new url
			return self::getURL($url, $id, $parentId);
		}

		// return the unique url!
		return $url;
	}


	/**
	 * Insert a page
	 *
	 * @return	int
	 * @param	array $page
	 */
	public static function insert(array $page)
	{
		// get db
		$db = BackendModel::getDB();

		// insert
		$id = (int) $db->insert('pages', $page);

		// rebuild the cache
		self::buildCache();

		// return the new revision id
		return $id;
	}


	/**
	 * Insert multiple blocks at once
	 *
	 * @return	void
	 * @param	array $blocks
	 */
	public static function insertBlocks(array $blocks, $hasBlock = false)
	{
		// get db
		$db = BackendModel::getDB();

		// rebuild value for has_extra
		$hasExtra = ($hasBlock) ? 'Y' : 'N';

		// update page
		$db->update('pages', array('has_extra' => $hasExtra), 'revision_id = ? AND status = ?', array($blocks[0]['revision_id'], 'active'));

		// insert
		$db->insert('pages_blocks', $blocks);
	}


	/**
	 * Inserts a new template
	 *
	 * @return	int
	 * @param	array $template
	 */
	public static function insertTemplate(array $template)
	{
		// get db
		$db = BackendModel::getDB();

		// default?
		if($template['is_default'] == 'Y') $db->update('pages_templates', array('is_default' => 'N'));

		// insert
		return (int) $db->insert('pages_templates', $template);
	}


	/**
	 * Move a page
	 *
	 * @return	bool
	 * @param	int $id
	 * @param	int $droppedOn
	 * @param	string $typeOfDrop
	 * @param	string[optional] $language
	 */
	public static function move($id, $droppedOn, $typeOfDrop, $language = null)
	{
		// redefine
		$id = (int) $id;
		$droppedOn = (int) $droppedOn;
		$typeOfDrop = SpoonFilter::getValue($typeOfDrop, array('before', 'after', 'inside'), 'inside');
		$language = ($language === null) ? BackendLanguage::getWorkingLanguage() : (string) $language;

		// get db
		$db = BackendModel::getDB();

		// reset type of drop for special pages
		if($droppedOn == 1) $typeOfDrop = 'inside';

		// get data for pages
		$page = self::get($id, $language);
		$droppedOnPage = self::get($droppedOn, $language);

		// validate
		if(empty($page) || empty($droppedOn)) return false;

		// calculate new parent for items that should be moved inside
		if($typeOfDrop == 'inside')
		{
			// check if item allows children
			if($page['allow_children'] != 'Y') return false;

			// set new parent to the dropped on page.
			$newParent = $droppedOnPage['id'];
		}

		// if the item has to be moved before or after
		else $newParent = $droppedOnPage['parent_id'];

		// decide new type
		$newType = 'page';
		if($droppedOnPage['type'] == 'meta') $newType = 'meta';
		if($droppedOnPage['type'] == 'footer') $newType = 'footer';
		if($droppedOnPage['type'] == 'root') $newType = 'root';

		// calculate new sequence for items that should be moved inside
		if($typeOfDrop == 'inside')
		{
			// get highest sequence
			$newSequence = (int) $db->getVar('SELECT p.sequence
												FROM pages AS p
												WHERE p.id = ? AND p.language = ? AND p.status = ?
												ORDER BY p.sequence DESC
												LIMIT 1;',
												array($newParent, $language, 'active')) + 1;

			// update
			$db->update('pages', array('parent_id' => $newParent, 'sequence' => $newSequence, 'type' => $newType), 'id = ? AND language = ? AND status = ?', array($id, $language, 'active'));
		}

		// calculate new sequence for items that should be moved before
		elseif($typeOfDrop == 'before')
		{
			// get new sequence
			$newSequence = (int) $db->getVar('SELECT p.sequence
												FROM pages AS p
												WHERE p.id = ? AND p.language = ? AND p.status = ?
												LIMIT 1;',
												array($droppedOnPage['id'], $language, 'active')) - 1;

			// increment all pages with a sequence that is higher or equal to the current sequence;
			$db->execute('UPDATE pages
							SET sequence = sequence + 1
							WHERE parent_id = ? AND language = ? AND sequence >= ?;',
							array($newParent, $language, $newSequence + 1));

			// update
			$db->update('pages', array('parent_id' => $newParent, 'sequence' => $newSequence, 'type' => $newType), 'id = ? AND language = ? AND status = ?', array($id, $language, 'active'));
		}

		// calculate new sequence for items that should be moved after
		elseif($typeOfDrop == 'after')
		{
			// get new sequence
			$newSequence = (int) $db->getVar('SELECT p.sequence
												FROM pages AS p
												WHERE p.id = ? AND p.language = ? AND p.status = ?
												LIMIT 1;',
												array($droppedOnPage['id'], $language, 'active')) + 1;

			// increment all pages with a sequence that is higher then the current sequence;
			$db->execute('UPDATE pages
							SET sequence = sequence + 1
							WHERE parent_id = ? AND language = ? AND sequence > ?;',
							array($newParent, $language, $newSequence));

			// update
			$db->update('pages', array('parent_id' => $newParent, 'sequence' => $newSequence, 'type' => $newType), 'id = ? AND language = ? AND status = ?', array($id, $language, 'active'));
		}

		// fallback
		else return false;

		// rebuild cache
		self::buildCache();

		// return
		return true;
	}


	/**
	 * Update a page
	 *
	 * @return	int
	 * @param	array $page
	 */
	public static function update(array $page)
	{
		// get db
		$db = BackendModel::getDB();

		// update old revisions
		if($page['status'] != 'draft') $db->update('pages', array('status' => 'archive'), 'id = ?', (int) $page['id']);
		else $db->delete('pages', 'id = ? AND user_id = ? AND status = ?', array((int) $page['id'], BackendAuthentication::getUser()->getUserId(), 'draft'));

		// insert
		$id = (int) $db->insert('pages', $page);

		// how many revisions should we keep
		$rowsToKeep = (int) BackendModel::getSetting('pages', 'maximum_number_of_revisions', 20);

		// get revision-ids for items to keep
		$revisionIdsToKeep = (array) $db->getColumn('SELECT p.revision_id
														FROM pages AS p
														WHERE p.id = ? AND p.status = ?
														ORDER BY p.edited_on DESC
														LIMIT ?;',
														array($page['id'], 'archive', $rowsToKeep));

		// delete other revisions
		if(!empty($revisionIdsToKeep))
		{
			// because blocks are linked by revision we should get all revisions we want to delete
			$revisionsToDelete = (array) $db->getColumn('SELECT p.revision_id
															FROM pages AS p
															WHERE p.id = ? AND status = ? AND revision_id NOT IN('. implode(', ', $revisionIdsToKeep) .')',
															array((int) $page['id'], 'archive'));

			// any revisions to delete
			if(!empty($revisionsToDelete))
			{
				$db->delete('pages', 'revision_id IN('. implode(', ', $revisionsToDelete) .')');
				$db->delete('pages_blocks', 'revision_id IN('. implode(', ', $revisionsToDelete) .')');
			}
		}

		// rebuild the cache
		self::buildCache();

		// return the new revision id
		return $id;
	}


	/**
	 * Update the blocks
	 *
	 * @return	void
	 * @param	array $blocks
	 */
	public static function updateBlocks(array $blocks, $hasBlock = false)
	{
		// get db
		$db = BackendModel::getDB();

		// rebuild value for has_extra
		$hasExtra = ($hasBlock) ? 'Y' : 'N';

		// update page
		$db->update('pages', array('has_extra' => $hasExtra), 'revision_id = ? AND status = ?', array($blocks[0]['revision_id'], 'active'));

		// update old revisions
		$db->update('pages_blocks', array('status' => 'archive'), 'id = ?', $blocks[0]['id']);

		// insert
		$db->insert('pages_blocks', $blocks);
	}


	/**
	 * @todo	PHPDoc
	 * @param unknown_type $id
	 * @param array $template
	 */
	public static function updateTemplate($id, array $template)
	{
		// get db
		$db = BackendModel::getDB();

		// update old revisions
		if($template['is_default'] == 'Y') $db->update('pages_templates', array('is_default' => 'N'), '');

		// update item
		$db->update('pages_templates', $template, 'id = ?', (int) $id);
	}
}

?>