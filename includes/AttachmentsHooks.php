<?php
class AttachmentsHooks {
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook('attach', [ self::class, 'renderAttach' ]);
		$parser->setFunctionHook('exturl', [ self::class, 'renderExtURL' ]);
		$parser->setFunctionHook('fileprefix', [ self::class, 'renderFilePrefix'], SFH_NO_HASH);
	}

	private static function msg($msg, $class=''){
		return "* <big class='ext-attachments $class'>$msg</big>";
	}

	public static function renderAttach( Parser $parser, $page) {
		$title = Title::newFromText($page);
		$parser->getOutput()->setProperty(Attachments::getAttachPropname($title), $title);

		$parser->getOutput()->setProperty(Attachments::PROP_ATTACH, true); # allow querying with API:Pageswithprop
		if ($parser->getTitle()->inNamespace(NS_FILE))
			# add category for $wgCountCategorizedImagesAsUsed
			$parser->getOutput()->addTrackingCategory('attachments-category-attached-files', $parser->getTitle());

		return [self::msg(wfMessage('attached-to').' <b>'.Linker::linkKnown($title, null, [], ['redirect'=>'no']).'</b>'), 'isHTML'=>true];
	}

	public static function renderExtURL( Parser $parser, $url) {
		$out = $parser->getOutput();
		if ($out->getExtensionData('did-exturl')){
			$parser->getOutput()->addTrackingCategory('attachments-category-exturl-error', $parser->getTitle());
			return self::msg(wfMessage('attachments-exturl-twice'), 'error');
		}

		$out->setExtensionData('did-exturl', true);
		$status = Attachments::validateURL($url);

		if ($status === true){
			$out->setProperty(Attachments::PROP_URL, $url);
			return self::msg("&rarr; $url");
		} else {
			$out->setProperty(Attachments::PROP_URL, 'invalid');
			$out->addTrackingCategory('attachments-category-exturl-error', $parser->getTitle());
			return self::msg($status.' '.wfEscapeWikiText($url), 'error');
		}
	}

	public static function renderFilePrefix( Parser $parser, $path) {
		$level = substr_count($path.'/', '../');
		$parts = explode('/', $parser->getTitle()->getPrefixedText(), 25);
		return Attachments::getFilePrefix(implode('/', array_slice($parts, 0, count($parts)-$level)));
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		if (!Attachments::isViewingApplicablePage($out)) return true;

		$title = $out->getTitle();

		$pages = Attachments::getPages($title);
		$files = Attachments::getFiles($title);
		$html = Attachments::makeList($title, $pages, $files, $out->getContext());

		if (count($pages)+count($files) > 0 || Hooks::run('ShowEmptyAttachmentsSection', [clone $title])){
			$out->addHTML("<div id=ext-attachments class=mw-parser-output>"); # class for external link icon
			$out->addWikitext("== ".wfMessage('attachments')."==");

			if ($skin->getSkinName() == 'minerva' && substr($out->mBodytext, -6) == '</div>')
				# hack to make section collapsible (removing </div>)
				$out->mBodytext = substr($out->mBodytext, 0, -6);

			$out->addHTML($html);
			if ($skin->getSkinName() == 'minerva')
				$out->addHTML('</div>');
			$out->addHTML("</div>");
		}
		if ($skin->getSkinName() == 'minerva')
			$out->addModules('ext.attachments.minerva-icon');
	}

	public static function onMinervaPreRender( MinervaTemplate $tpl ) {
		if (!Attachments::isViewingApplicablePage($tpl->getSkin()) || Attachments::hasExtURL($tpl->getSkin()->getTitle()))
			return;

		$title = $tpl->getSkin()->getTitle();

		if (Attachments::countAttachments($title) > 0 || Hooks::run('ShowEmptyAttachmentsSection', [clone $title]))
			$tpl->data['page_actions']['attachments'] = [
				'itemtitle' => wfMessage('attachments'),
				'href' => '#' . Sanitizer::escapeIdForAttribute(wfMessage('attachments')),
				'class' => 'mw-ui-icon mw-ui-icon-element mw-ui-icon-minerva-attachments'
			];

		$tpl->data['page_actions']['attach'] = [
			'itemtitle' => wfMessage('attachments-add-new'),
			'href' => Title::newFromText('Special:Attach/' . $title->getPrefixedText())->getLocalURL(),
			'class' => 'mw-ui-icon mw-ui-icon-element mw-ui-icon-minerva-attach'
		];
	}

	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		if (!Attachments::isViewingApplicablePage($sktemplate) || Attachments::hasExtURL($sktemplate->getTitle()))
			return;

		$title = $sktemplate->getTitle();

		$count = Attachments::countAttachments($title);
		if ($count > 0 || Hooks::run('ShowEmptyAttachmentsSection', [clone $title]))
			$links['namespaces'] = array_slice($links['namespaces'], 0, 1) + [
				'attachments' => [
					'text'=> wfMessage('attachments') . " ($count)",
					'href' => '#' . wfMessage('attachments')
				]
			] + array_slice($links['namespaces'], 1);
		$links['views'] = array_slice($links['views'], 0, 2) + [
			'add_attachment' => [
				'text'=> wfMessage('attachments-verb'),
				'href' => Title::newFromText('Special:Attach/'.$title->getPrefixedText())->getLocalURL(),
				'class' => ''
			]
		] + array_slice($links['views'], 2);

		return true;
	}

	public static function onListDefinedTags( &$tags ) {
		$tags[] = 'attachments-add-exturl';
	}

	public static function onMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'fileprefix';
	}
	public static function onParserGetVariableValueSwitch( &$parser, &$cache, &$magicWordId, &$ret, &$frame ) {
		if ($magicWordId == 'fileprefix')
			$ret = Attachments::getFilePrefix($parser->getTitle()->getPrefixedText());
		return true;
	}
}
