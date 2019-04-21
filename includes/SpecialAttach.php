<?php
class SpecialAttach extends SpecialUpload {
	function __construct() {
		SpecialPage::__construct( 'Attach' );
	}

	function execute( $arg ) {
		$req = $this->getRequest();

		if ($req->wasPosted())
			$this->arg = $this->getRequest()->getText('wpArg');
		else
			$this->arg = $arg;

		$out = $this->getOutput();
		if (empty($this->arg)){
			$out->addWikiText(wfMessage('notargettext'));
			$out->setPageTitle(wfMessage('notargettitle'));
			return;
		}
		$t = Title::newFromText($this->arg);
		if (!$t || !$t->exists()){
			$out->addWikiText(wfMessage('nopagetext'));
			$out->setPageTitle(wfMessage('nopagetitle'));
			return;
		}
		$this->arg = $t->getPrefixedText();

		$prefix = $this->prefix = Attachments::getFilePrefix($this->arg);
		if ($req->wasPosted()){
			$this->arg = $this->getRequest()->getText('wpArg');

			if (!empty($req->getText('wpDestFile'))) {
				if (strpos($req->getText('wpDestFile'), $prefix) !== 0)
					$req->setVal('wpDestFile', $prefix . $req->getText('wpDestFile'));
			} elseif ($req->getFileName( 'wpUploadFile' ) !== null){
				if (strpos($req->getFileName('wpUploadFile'), $prefix) !== 0)
					$_FILES['wpUploadFile']['name'] = $prefix . $req->getFileName('wpUploadFile');
			}
		}
		parent::execute($arg);
		$out->setPageTitle(wfMessage('attach-page', $arg)->escaped());
	}

	function showUploadForm($form){
		$this->getSkin()->setRelevantTitle(Title::newFromText($this->arg));
		$out = $this->getOutput();

		(new SubpageForm($this->arg, $this->getContext()))->show();

		# hiding prefix using a hack to circumvent protected scope
		(function ($outer){
			$this->mDefault = substr($this->mDefault, strlen($outer->prefix));
		})->call($form->getField('DestFile'), $this);

		$form->addHiddenField('wpArg', $this->arg);
		$out->addHTML('<h2>'.wfMessage('attachments-heading-upload')->escaped().'</h2>');
		parent::showUploadForm($form);

		# the JavaScript doesn't know about the prefix we add
		$out->addJsConfigVars(['wgAjaxUploadDestCheck'=>false]);

		(new LinkForm($this->arg, $this->getContext()))->show();
	}

	function processUpload() {
		$f = $this->arg;
		$this->mComment .= "\n{{#attach:$f}}";
		parent::processUpload();
	}
}

class SubpageForm extends HTMLForm {
	function __construct($arg, $context) {
		parent::__construct([
			'Subpage'=> [
				'type' => 'text',
				'required' => true,
				'validation-callback' => [self::class, 'validateSubpage'],
				'label' => wfMessage('attachments-subpage-name'),
			], 'Arg' => [
				'type' => 'hidden',
				'default' => $arg
			]
		], $context);
		$this->setSubmitCallback([$this, 'submit']);
		$this->setFormIdentifier('add-subpage');
		$this->setSubmitText(wfMessage('attachments-action-add-subpage'));
		$this->setAutocomplete('off');
		$this->addPreText('<h2>'.wfMessage('attachments-heading-add-subpage')->escaped().'</h2>');
	}

	static function validateSubpage($subpage, $data){
		if ($subpage === null)
			return true;
		$title = Title::newFromText($data['Arg'])->getSubpage(Title::capitalize($subpage));
		if ($title === null)
			return wfMessage('attachments-invalidtitle');
		if ($title->exists())
			return wfMessage('attachments-articleexists', $title->getPrefixedText());
		return true;
	}

	function submit($data){
		$this->getOutput()->redirect(
			Title::newFromText($data['Arg'])
			->getSubpage(Title::capitalize($data['Subpage']))
			->getFullURL(['action'=>'edit'])
		);
	}
}

class  LinkForm extends HTMLForm {
	function __construct($arg, $context) {
		parent::__construct([
			'Arg' => [
				'type' => 'hidden',
				'default' => $arg
			], 'Subpage' => [
				'type' => 'text',
				'required' => true,
				'validation-callback' => [SubpageForm::class, 'validateSubpage'],
				'label' => wfMessage('attachments-title')
			], 'URL'=> [
				'type' => 'url',
				'required' => true,
				'validation-callback' => [Attachments::class, 'validateURL'],
				'label' => 'URL'
			]
		], $context);
		$this->setSubmitCallback([$this, 'submit']);
		$this->setFormIdentifier('add-link');
		$this->setSubmitText(wfMessage('attachments-action-add-link'));
		$this->addPreText('<h2>'.wfMessage('attachments-heading-add-link')->escaped().'</h2>');
		$this->addPreText(wfMessage('attachments-addlinktext')->parse());
		$this->setAutocomplete('off');
	}

	function submit($data){
		$status = WikiPage::factory(
			Title::newFromText($data['Arg'])
			->getSubpage(Title::capitalize($data['Subpage']))
		)->doEditContent(
			new WikitextContent("{{#exturl:${data['URL']}}}"),
			wfMessage('attachments-exturl-edit-msg'), EDIT_NEW, false, null, null, ['attachments-add-exturl']
		);
		$this->getOutput()->redirect(Title::newFromText($data['Arg'])->getFullURL());
	}
}
