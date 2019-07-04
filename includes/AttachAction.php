<?php
class AttachAction extends Action {
	public function getName(){
		return 'attach';
	}
	public function show(){
		$special = new SpecialAttach();
		$special->execute($this->getTitle());
	}
}

class SpecialAttach extends SpecialUpload {
	function __construct() {
		SpecialPage::__construct( 'Attach' );
	}

	function execute( $title ) {
		$req = $this->getRequest();

		$this->title = $title;
		$out = $this->getOutput();

		$prefix = $this->prefix = Attachments::getFilePrefix($title);

		if ($req->wasPosted()){
			if (!empty($req->getText('wpDestFile'))) {
				if (strpos($req->getText('wpDestFile'), $prefix) !== 0)
					$req->setVal('wpDestFile', $prefix . $req->getText('wpDestFile'));
			} elseif ($req->getFileName( 'wpUploadFile' ) !== null){
				if (strpos($req->getFileName('wpUploadFile'), $prefix) !== 0)
					$_FILES['wpUploadFile']['name'] = $prefix . $req->getFileName('wpUploadFile');
			}
		}
		$out->addWikiText(wfMessage('attach-text'));
		parent::execute('');
		$out->setPageTitle(wfMessage('attach-page', $title->getPrefixedText())->escaped());
	}

	function showUploadForm($form){
		$out = $this->getOutput();

		(new SubpageForm($this->getContext()))->show();

		# hiding prefix using a hack to circumvent protected scope
		(function ($outer){
			$this->mDefault = substr($this->mDefault, strlen($outer->prefix));
		})->call($form->getField('DestFile'), $this);

		$form->setAction($this->title->getFullURL(['action'=>'attach']));
		$form->setTitle($this->title);

		$out->addHTML('<h2>'.wfMessage('attach-upload-heading')->escaped().'</h2>');
		parent::showUploadForm($form);

		# the JavaScript doesn't know about the prefix we add
		$out->addJsConfigVars(['wgAjaxUploadDestCheck'=>false]);

		(new LinkForm($this->getContext()))->show();
	}

	function processUpload() {
		$f = $this->title;
		$this->mComment .= "\n{{#attach:$f}}";
		parent::processUpload();
	}
}

class SubpageForm extends HTMLForm {
	function __construct($context) {
		parent::__construct([
			'Subpage'=> [
				'type' => 'text',
				'required' => true,
				'validation-callback' => [self::class, 'validateSubpage'],
				'label' => wfMessage('attach-addsubpage-label'),
			]
		], $context);
		$this->setSubmitCallback([$this, 'submit']);
		$this->setFormIdentifier('add-subpage');
		$this->setSubmitText(wfMessage('attach-addsubpage-action'));
		$this->setAutocomplete('off');
		$this->addPreText('<h2>'.wfMessage('attach-addsubpage-heading')->escaped().'</h2>');
		$this->addPreText(wfMessage('attach-addsubpage-text')->parseAsBlock());
		$this->setAction($this->getTitle()->getFullURL(['action'=>'attach']));
	}

	static function validateSubpage($subpage, $data){
		global $wgRequest;
		if ($subpage === null)
			return true;
		$title = Title::newFromText($wgRequest->getVal('title'))->getSubpage(Title::capitalize($subpage));
		if ($title === null)
			return wfMessage('attach-invalidtitle');
		if ($title->exists())
			return wfMessage('attach-articleexists', $title->getPrefixedText());
		return true;
	}

	function submit($data){
		$this->getOutput()->redirect(
			Title::newFromText($this->getRequest()->getVal('title'))
			->getSubpage(Title::capitalize($data['Subpage']))
			->getFullURL(['action'=>'edit'])
		);
	}
}

class  LinkForm extends HTMLForm {
	function __construct($context) {
		parent::__construct([
			'Subpage' => [
				'type' => 'text',
				'required' => true,
				'validation-callback' => [SubpageForm::class, 'validateSubpage'],
				'label' => wfMessage('attach-addlink-title')
			], 'URL'=> [
				'type' => 'url',
				'required' => true,
				'validation-callback' => [Attachments::class, 'validateURL'],
				'label' => wfMessage('attach-addlink-url')
			]
		], $context);
		$this->setSubmitCallback([$this, 'submit']);
		$this->setFormIdentifier('add-link');
		$this->setSubmitText(wfMessage('attach-addlink-action'));
		$this->addPreText('<h2>'.wfMessage('attach-addlink-heading')->escaped().'</h2>');
		$this->addPreText(wfMessage('attach-addlink-text')->parseAsBlock());
		$this->setAutocomplete('off');
		$this->setAction($this->getTitle()->getFullURL(['action'=>'attach']));
	}

	function submit($data){
		$title = Title::newFromText($this->getRequest()->getVal('title'));
		$status = WikiPage::factory(
			$title->getSubpage(Title::capitalize($data['Subpage']))
		)->doEditContent(
			new WikitextContent("{{#exturl:${data['URL']}}}"),
			wfMessage('attach-addlink-editmsg'), EDIT_NEW, false, null, null, ['attachments-add-exturl']
		);
		$this->getOutput()->redirect($title->getFullURL());
	}
}
