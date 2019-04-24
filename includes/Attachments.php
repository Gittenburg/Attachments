<?php
class Attachments {
	public static function getFilePrefix($title){
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
			$ctx->getRequest()->getText('action', 'view') == 'view';
	}
	const PROP_URL = 'attachments-url';
	const PROP_ATTACH = 'attach';

	public static function hasExtURL($title){
		return !empty(PageProps::getInstance()->getProperties($title, self::PROP_URL));
	}

	public static function getAttachPropname($title){
		# appending hash to propname because properties cannot have
		# multiple values, and titles may not fit in the propname column
		return self::PROP_ATTACH . '-' . md5($title);
	}

	public static function countAttachments($title){
		return self::getFiles($title, true) + self::getSubpages($title, true);
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

	public static function getSubpages(Title $title, $count = FALSE){
		$dbr = wfGetDB(DB_REPLICA);
		$results = [];
		$res = $dbr->select(
			['page', 'rev'=>'revision', 'patt'=>'page_props', 'purl' => 'page_props'],
			$count ? ['count'=>'count(*)'] : ['page_title', 'page_namespace', 'purl.pp_value'],
			[
				$dbr->makeList([
					$dbr->makeList([
						'page_title'.$dbr->buildLike($title->getDBkey().'/', $dbr->anyString()),
						'page_namespace'=>$title->getNamespace()
					], LIST_AND),
					'patt.pp_propname is not null'
				], LIST_OR),
				'page_is_redirect=0',
				'page_namespace !=' . NS_FILE
			],
			__METHOD__,
			[],
			[
				'rev'=>['INNER JOIN', ['page_latest=rev_id', 'rev_deleted=0']],
				'patt'=>['LEFT JOIN', ['page_id=patt.pp_page', 'patt.pp_propname'=>self::getAttachPropname($title)]],
				'purl'=>['LEFT JOIN', ['page_id=purl.pp_page', 'purl.pp_propname'=>self::PROP_URL]],
			]
		);
		if ($count)
			return $res->fetchObject()->count;
		foreach ($res as $row){
			$results[] = ["title"=> Title::newFromRow($row), "url"=>$row->pp_value];
		}
		return $results;
	}

	private static function getDetailLink($title){
		return " (".Linker::linkKnown($title, "details").")";
	}

	public static function makeList(Title $title, $context) {
		$links = [];

		foreach( self::getSubpages($title) as $res ) {
			$subtitle = $res['title'];
			if (strpos($subtitle->getPrefixedText(), $title->getPrefixedText() . '/') === 0)
				$subtitle = substr($subtitle, strlen($title)+1);
			$key = mb_convert_case($subtitle, MB_CASE_UPPER, 'UTF-8');
			if ($subtitle == ''){
				$subtitle = '/';
				$key = ' ';
			}
			if ($res['url']) {
				if ($res['url'] == 'invalid')
					$links[$key] = '<a class=new title="'.wfMessage('attachments-invalid-url')->escaped().'">'
									.htmlspecialchars($subtitle).'<span class=external></span></a>';
				else
					$links[$key] = Linker::makeExternalLink($res['url'], $subtitle);
				$links[$key] .= self::getDetailLink($res['title']);
			} else
				$links[$key] = Linker::linkKnown($res['title'], $subtitle);
		}

		foreach( self::getFiles($title) as $file ) {
			$label = $file->getTitle()->getText();
			if (strpos($label, self::getFilePrefix($title)) === 0)
				$label = substr($label, strlen(self::getFilePrefix($title)));
			$links[$label] = Linker::makeMediaLinkFile($file->getTitle(), $file, $label)
				. self::getDetailLink($file->getTitle());
		}

		if (count($links) > 0){
			if (Hooks::run('BeforeSortAttachments', [&$links]))
				ksort($links);

			$articles_start_char = [];
			$articles = [];

			foreach( $links as $key=>$link ) {
				$articles[] = $link;
				$articles_start_char[] = mb_substr($key, 0, 1);
			}
			return Linker::linkKnown(SpecialPage::getTitleFor('Attach', $title->getPrefixedText()), wfMessage('attachments-add-new'))
				. (new CategoryViewer($title, $context))->formatList($articles, $articles_start_char);
		}
	}
}
