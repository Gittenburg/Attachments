<?php
use MediaWiki\MediaWikiServices;

class Attachments {
	const PROP_URL = 'attachments-url';
	const PROP_ATTACH = 'attach';
	const PROP_IGNORE_SUBPAGES = 'attachments-ignore-subpages';

	public static function getFilePrefix($title){
		if (empty($title))
			return '';
		return str_replace([':', '/'], '-', $title).' - ';
	}

	public static function validateURL($url, $allData=null){
		if (!filter_var($url, FILTER_VALIDATE_URL))
			return wfMessage('attachments-invalid-url');
		return true;
	}

	public static function mayHaveAttachments($title){
		global $wgAttachmentsNamespaces;
		return $title->canExist() && ($wgAttachmentsNamespaces[$title->getNamespace()] ?? false);
	}

	public static function isViewingApplicablePage($ctx){
		return $ctx->getTitle()->exists() &&
			self::mayHaveAttachments($ctx->getTitle()) &&
			$ctx->getOutput()->isArticle() &&
			($ctx->getOutput()->getRevisionId() == 0 || $ctx->getOutput()->getRevisionId() == $ctx->getTitle()->getLatestRevID());
	}

	public static function hasExtURL($title){
		return !empty(PageProps::getInstance()->getProperties($title, self::PROP_URL));
	}

	public static function getAttachPropname($title){
		# appending hash to propname because properties cannot have
		# multiple values, and titles may not fit in the propname column
		return self::PROP_ATTACH . '-' . md5($title);
	}

	public static function countAttachments($title){
		return self::getFiles($title, true) + self::getPages($title, true);
	}

	public static function getFiles($title, $count = FALSE){
		$dbr = wfGetDB(DB_REPLICA);
		$res = $dbr->select(
			['page_props', 'page'],
			$count ? ['count'=>'count(*)'] : ['page_title'],
			[
				'page_namespace'=>NS_FILE,
				'page_id=pp_page',
				'pp_propname'=>self::getAttachPropname($title)
			]
		);
		if ($count)
			return $res->fetchObject()->count;
		$titles = [];
		foreach( $res as $row ) {
			$titles[] = $row->page_title;
		}
		return RepoGroup::singleton()->getLocalRepo()->findFiles($titles);
	}

	public static function getPages(Title $title, $count = FALSE){
		$dbr = wfGetDB(DB_REPLICA);
		$subpageCond = [
			'page_title'.$dbr->buildLike($title->getDBkey().'/', $dbr->anyString()),
			'page_namespace'=>$title->getNamespace()
		];
		foreach (PageProps::getInstance()->getProperties($title, self::PROP_IGNORE_SUBPAGES) as $id => $pattern){
			$subpageCond[] = 'page_title NOT '.$dbr->buildLike($title->getDBKey() . '/' . $pattern, $dbr->anyString());
		}

		$res = $dbr->select(
			['page', 'rev'=>'revision', 'patt'=>'page_props', 'purl' => 'page_props', 'pp_defaultsort' => 'page_props'],
			$count ? ['count'=>'count(*)'] : ['page_title', 'page_namespace', 'purl.pp_value', 'defaultsort'=>'pp_defaultsort.pp_value'],
			[
				$dbr->makeList([$dbr->makeList($subpageCond, LIST_AND), 'patt.pp_propname is not null'], LIST_OR),
				'page_is_redirect = 0',
				'page_namespace !=' . NS_FILE
			],
			__METHOD__,
			[],
			[
				'rev'=>['INNER JOIN', ['page_latest=rev_id', 'rev_deleted=0']],
				'patt'=>['LEFT JOIN', ['page_id=patt.pp_page', 'patt.pp_propname'=>self::getAttachPropname($title)]],
				'purl'=>['LEFT JOIN', ['page_id=purl.pp_page', 'purl.pp_propname'=>self::PROP_URL]],
				'pp_defaultsort'=>['LEFT JOIN', ['page_id=pp_defaultsort.pp_page', 'pp_defaultsort.pp_propname=\'defaultsort\'']]
			]
		);
		if ($count)
			return $res->fetchObject()->count;

		$results = [];
		foreach ($res as $row){
			$results[] = ["title"=> Title::newFromRow($row), "url"=>$row->pp_value, 'defaultsort'=>$row->defaultsort];
		}
		return $results;
	}

	private static function getDetailLink($linkRenderer, $title){
		return " (".$linkRenderer->makeKnownLink($title, "details").")";
	}

	private static function stripTitle(string $subtitle, string $title){
		if (strpos($subtitle, $title . '/') === 0)
			$subtitle = substr($subtitle, strlen($title) + 1);
		return $subtitle;
	}

	public static function makeList(Title $title, $pages, $files, $context) {
		global $wgAttachmentsChunkListByLetter;
		$links = [];

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		foreach( $pages as $res ) {
			$subtitle = self::stripTitle($res['title']->getPrefixedText(), $title->getPrefixedText());

			if (strpos($subtitle, '/') !== false && $res['title']->getBaseTitle()->exists())
				continue; # hide subsubpages

			$key = mb_convert_case($res['defaultsort'] ? self::stripTitle($res['defaultsort'], $title->getText()) : $subtitle, MB_CASE_UPPER, 'UTF-8');
			if ($subtitle == ''){
				$subtitle = '/';
				$key = ' ';
			}

			if (array_key_exists($key, $links))
				$key .= ' '; # prevent possible overwrites through defaultsort

			if ($res['url']) {
				if ($res['url'] == 'invalid')
					$links[$key] = '<a class=new title="'.wfMessage('attachments-invalid-url')->escaped().'">'
									.htmlspecialchars($subtitle).'<span class=external></span></a>';
				else
					$links[$key] = Linker::makeExternalLink($res['url'], $subtitle);
				$links[$key] .= self::getDetailLink($linkRenderer, $res['title']);
			} else
				$links[$key] = $linkRenderer->makeKnownLink($res['title'], $subtitle);
		}

		foreach( $files as $file ) {
			$label = $file->getTitle()->getText();
			if (strpos($label, self::getFilePrefix($title)) === 0)
				$label = substr($label, strlen(self::getFilePrefix($title)));
			$links[mb_convert_case($label, MB_CASE_UPPER, 'UTF-8')] = Linker::makeMediaLinkFile($file->getTitle(), $file, $label)
				. self::getDetailLink($linkRenderer, $file->getTitle());
		}

		if (count($links) == 0){
			return wfMessage('attachments-add-first', $linkRenderer->makeKnownLink($title, wfMessage('attachments-add-first-link'), [], ['action'=>'attach']))->text();
		} else {
			if (Hooks::run('BeforeSortAttachments', [&$links]))
				ksort($links);

			$articles_start_char = [];
			$articles = [];

			foreach( $links as $key=>$link ) {
				$articles[] = $link;
				$articles_start_char[] = mb_substr($key, 0, 1);
			}
			if ($wgAttachmentsChunkListByLetter) {
				// Both columnList and shortList chunk the list items by their first letter.
				// Like MediaWiki categories we only use the three-column format if there are more than 6 items.
				if (count($articles) > 6) {
					$listHTML = CategoryViewer::columnList($articles, $articles_start_char);
				} else {
					$listHTML = CategoryViewer::shortList($articles, $articles_start_char);
				}
			} else {
				$listHTML = '<ul><li>' . implode( "</li>\n<li>", $articles ) . '</li></ul>';
			}
			return $linkRenderer->makeKnownLink($title, wfMessage('attachments-add-new'), [], ['action'=>'attach'])
				. $listHTML;
		}
	}
}
