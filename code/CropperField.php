<?php namespace CropperField;

use CropperField\Cropper\GD as GDCropper;
use CropperField\AdapterInterface;
use DataObjectInterface;
use Requirements;
use DataObject;
use FormField;
use Director;
use JSConfig;
use Image;
use Debug;
use Form;

class CropperField extends FormField {

	protected static $default_options = array(
	    'aspect_ratio' => 1,
	    'min_height' => 128,
	    'max_height' => 128,
	    'min_width' => 128,
	    'max_width' => 128,
	);

	protected $record;

	public function __construct(
		$name,
		$title = null,
		AdapterInterface $adapter,
		array $options = array()
	) {
		parent::__construct($name, $title);
		$this->setAdapter($adapter);
		$this->setTemplate('CropperField');
		$this->addExtraClass('stacked');
		$this->setOptions($options);
	}

	/**
	 * @param \Form $form
	 */
	public function setForm($form) {
		$this->getAdapter()->getFormField()->setForm($form);
		return parent::setForm($form);
	}

	/**
	 * @param array $options
	 * @return $this
	 */
	public function setOptions(array $options) {
		$defaults = static::$default_options;
		$this->options = array_merge($defaults, $options);
		return $this;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * @return string
	 */
	public function getOption($name) {
		return $this->options[$name];
	}

	public function getAdapter() {
		return $this->adapter;
	}

	public function setAdapter(AdapterInterface $adapter) {
		$this->adapter = $adapter;
		return $this;
	}

	public function saveInto(DataObjectInterface $object) {
		if(!$this->canCrop()) {
			return;
		}
		$object->setField($this->getName() . 'ID', $this->generateCropped()->ID);
		$object->write();
	}

	/**
	 * Force a record to be used as "Parent" for uploaded Files (eg a Page with a has_one to File)
	 * @param DataObject $record
	 */
	public function setRecord($record) {
		$this->record = $record;
		return $this;
	}
	/**
	 * Get the record to use as "Parent" for uploaded Files (eg a Page with a has_one to File) If none is set, it will
	 * use Form->getRecord() or Form->Controller()->data()
	 *
	 * @return DataObject
	 */
	public function getRecord() {
		if (!$this->record && $this->form) {
			if (($record = $this->form->getRecord()) && ($record instanceof DataObject)) {
				$this->record = $record;
			} elseif (($controller = $this->form->Controller())
				&& $controller->hasMethod('data')
				&& ($record = $controller->data())
				&& ($record instanceof DataObject)
			) {
				$this->record = $record;
			}
		}
		return $this->record;
	}

	public function getExistingThumbnail() {
		return $this->getRecord()->{$this->getName()}();
	}

	/**
	 * @return Image
	 */
	public function generateCropped() {
		$file = $this->getAdapter()->getFile();
		$thumbImage = new Image();
		$thumbImage->ParentID = $file->ParentID;
		$cropper = new GDCropper();
		$cropper->setCropData($this->getCropData());
		$cropper->setSourceImage($file);
		$cropper->setTargetWidth(
			$this->getOption('max_width')
		);
		$cropper->setTargetHeight(
			$this->getOption('max_height')
		);
		$cropper->crop($thumbImage);
		$thumbImage->write();
		return $thumbImage;
	}

	/**
	 * @return array
	 */
	public function getCropData() {
		$value = $this->Value();
		return json_decode($value['Data'], true);
	}

	/**
	 * If the tickbox on the frontend is unchecked, do not regenerate
	 * @return boolean
	 */
	public function canCrop() {
		$value = $this->Value();
		return !empty($value['Enabled']);
	}

	public function Field($properties = array()) {
		$this->requireFrontend();
		return parent::Field($properties);
	}

	/**
	 * Pull in the cropper.js requirements. If in dev mode, bring in unminified.
	 * @return void
	 */
	protected function requireFrontend() {
		$extension = Director::isLive() ? '.min' : '';
		$cssFile = CROPPERFIELD_PATH . '/cropper/cropper' . $extension . '.css';
		$jsFiles = array(
			CROPPERFIELD_PATH . '/cropper/cropper' . $extension . '.js',
			CROPPERFIELD_PATH . '/cropper/CropperField.js',
		);
		Requirements::css($cssFile);
		Requirements::combine_files('cropperfield-all.js', $jsFiles);
		JSConfig::add('CropperField', array(
			$this->getName() => $this->getOptions()
		));
		JSConfig::insert();
	}

}
