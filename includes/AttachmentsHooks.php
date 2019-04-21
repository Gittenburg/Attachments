<?php
class AttachmentsHooks {
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'attach', [ self::class, 'renderAttach' ]);
		$parser->setFunctionHook( 'exturl', [ self::class, 'renderExtURL' ]);
	}

	private static function msg($msg, $class=''){
		return "* <big class='ext-attachments $class'>$msg</big>";
	}

	public static function renderAttach( Parser $parser, $page) {
		$title = Title::newFromText($page);
		$parser->getOutput()->setProperty(Attachments::getAttachPropname($title), $title);
		$parser->getOutput()->setProperty(Attachments::PROP_ATTACH, true); # allow querying with API:Pageswithprop
		return [self::msg(wfMessage('attached-to').' <b>'.Linker::linkKnown($title, null, [], ['redirect'=>'no']).'</b>'), 'isHTML'=>true];
	}

	static $didExtURL = false;

	public static function renderExtURL( Parser $parser, $url) {
		if (self::$didExtURL){
			$parser->getOutput()->addTrackingCategory('attachments-category-exturl-error', $parser->getTitle());
			return self::msg(wfMessage('attachments-exturl-twice'), 'error');
		}
		self::$didExtURL = true;
		$status = Attachments::validateURL($url);
		if ($status === true){
			$parser->getOutput()->setProperty(Attachments::PROP_URL, $url);
			return self::msg("&rarr; $url");
		} else {
			$parser->getOutput()->setProperty(Attachments::PROP_URL, 'invalid');
			$parser->getOutput()->addTrackingCategory('attachments-category-exturl-error', $parser->getTitle());
			return self::msg($status.' '.wfEscapeWikiText($url), 'error');
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		if (!Attachments::isViewingApplicablePage($out)) return true;

		$html = Attachments::makeList($out->getTitle(), $out->getContext());

		if ($html != null){
			$out->addHTML("<div id=ext-attachments class=mw-parser-output>"); # class for external link icon
			$out->addWikitext("== ".wfMessage('attachments-noun')."==");

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
		$tpl->data['page_actions']['attach'] = [
			'class' => 'mw-ui-icon mw-ui-icon-element mw-ui-icon-minerva-attach',
			'text' => 'Anhängen',
			'href' => Title::newFromText('Special:Attach/'.$tpl->getSkin()->getTitle()->getText())->getLocalURL()
		];
	}

	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		if (!Attachments::isViewingApplicablePage($sktemplate) || Attachments::hasExtURL($sktemplate->getTitle()))
			return;

		$title = $sktemplate->getTitle();

		$count = Attachments::countAttachments($title);
		if ($count > 0)
			$links['namespaces'] = array_slice($links['namespaces'], 0, 1) + [
				'attachments' => [
					'text'=> wfMessage('attachments-noun') . " ($count)",
					'href' => '#' . wfMessage('attachments-noun')
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
			$ret = Attachments::getFilePrefix($parser->getTitle()->getText());
		return true;
	}
}
