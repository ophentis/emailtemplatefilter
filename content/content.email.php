<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');
	
	class ContentExtensionEmailBuilderEmail extends AdministrationPage {
		protected $email = null;
		protected $errors = null;
		
		public function build($context) {
			// Load existing email:
			if (isset($context[0]) && EmailBuilderEmail::exists($context[0])) {
				$this->email = EmailBuilderEmail::load($context[0]);
			}
			
			// Create new email:
			else {
				$this->email = new EmailBuilderEmail();
			}
			
			return parent::build($context);
		}
		
		public function action() {
			$root_url = dirname(Symphony::Engine()->getCurrentPageURL());
			$email = $this->email;
			
			// Update email with post data:
			if (isset($_POST['fields'])) {
				$email->setData($_POST['fields']);
			}
			
			if (isset($_POST['overrides'])) {
				$email->setOverrides($_POST['overrides']);
			}
			
			// Email passes validation:
			if ($email->validate()) {
				var_dump('-redirect-'); exit;
			}
			
			$this->pageAlert(
				__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
				Alert::ERROR
			);
		}
		
		public function view() {
			$email = $this->email;
			
			// Use 'Untitled' as page title when email name is empty:
			$title = (
				isset($email->data()->name) && trim($email->data()->name) != ''
					? $email->data()->name
					: __('Untitled')
			);
			
			$this->setPageType('form');
			$this->setTitle(__(
				(
					isset($email->data()->id)
						? '%1$s &ndash; %2$s &ndash; %3$s'
						: '%1$s &ndash; %2$s'
				),
				array(
					__('Symphony'),
					__('Pages'),
					$title
				)
			));
			$this->appendSubheading($title);
			$this->addScriptToHead(URL . '/extensions/emailbuilder/assets/email.js');
			
			$this->appendEssentialsFieldset($email, $this->Form);
			$this->appendContentFieldset($email, $this->Form);
			$this->appendTemplateFieldset($email, $this->Form);
			$this->appendOverridesFieldset($email, $this->Form);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					($this->_editing ? __('Save Changes') : __('Create Template')),
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);
			
			if ($this->_editing) {
				$button = new XMLElement('button', 'Delete');
				$button->setAttributeArray(array(
					'name'		=> 'action[delete]',
					'class'		=> 'confirm delete',
					'title'		=> __('Delete this template')
				));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
		}
		
		public function appendContentFieldset($email, $wrapper) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Content')));
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__(
				'To access the XML of your template page, use XPath expressions:<br /><code>%s</code>.',
				array('{datasource/entry/field-one}')
			));
			
			$fieldset->appendChild($help);
			
			// Subject:
			$label = Widget::Label(__('Subject'));
			$label->appendChild(Widget::Input(
				'fields[subject]',
				General::sanitize($email->data()->subject)
			));
			
			if (isset($email->errors()->subject)) {
				$label = Widget::wrapFormElementWithError($label, $email->errors()->subject);
			}
			
			$fieldset->appendChild($label);
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			// Sender Name
			$label = Widget::Label(__('Sender Name'));
			$label->appendChild(Widget::Input(
				'fields[sender_name]',
				General::sanitize($email->data()->sender_name)
			));
			
			if (isset($email->errors()->sender_name)) {
				$label = Widget::wrapFormElementWithError($label, $email->errors()->sender_name);
			}
			
			$group->appendChild($label);
			
			// Senders
			$label = Widget::Label(__('Sender Address'));
			$label->appendChild(Widget::Input(
				'fields[sender_address]',
				General::sanitize($email->data()->sender_address)
			));
			
			if (isset($email->errors()->sender_address)) {
				$label = Widget::wrapFormElementWithError($label, $email->errors()->sender_address);
			}
			
			$group->appendChild($label);
			$fieldset->appendChild($group);
			
			// Recipients
			$label = Widget::Label(__('Recipient Address'));
			$label->appendChild(Widget::Input(
				'fields[recipient_address]',
				General::sanitize($email->data()->recipient_address)
			));
			
			if (isset($email->errors()->recipient_address)) {
				$label = Widget::wrapFormElementWithError($label, $email->errors()->recipient_address);
			}
			
			$fieldset->appendChild($label);
			$wrapper->appendChild($fieldset);
		}
		
		public function appendEssentialsFieldset($email, $wrapper) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));
			
			if (!empty($email->data()->id)) {
				$fieldset->appendChild(Widget::Input(
					'fields[id]', $email->data()->id, 'hidden'
				));
			}
			
			// Name:
			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input(
				'fields[name]',
				General::sanitize($email->data()->name)
			));
			
			if (isset($email->errors()->name)) {
				$label = Widget::wrapFormElementWithError($label, $email->errors()->name);
			}
			
			$fieldset->appendChild($label);
			$wrapper->appendChild($fieldset);
		}
		
		public function appendOverridesFieldset($email, $wrapper) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Overrides')));
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('An override changes the above content and template when its expression matches an XML element or is <code>true()</code>.'));
			
			$fieldset->appendChild($help);
			
			$ol = new XMLElement('ol');
			$ol->setAttribute('id', 'email-conditions-duplicator');
			
			// Add existing conditions:
			foreach ($email->overrides() as $order => $override) {
				$item = new XMLElement('li');
				$this->appendOverrideItem($override, $item, order);
				$ol->appendChild($item);
			}
			
			// Add condition template:
			$item = new XMLElement('li');
			$item->setAttribute('class', 'template');
			$this->appendOverrideItem(new EmailBuilderOverride(), $item, -1);
			$ol->appendChild($item);
			
			$fieldset->appendChild($ol);
			$wrapper->appendChild($fieldset);
		}
		
		public function appendOverrideItem($override, $wrapper, $order) {
			$prefix = "overrides[{$order}]";
			
			$wrapper->appendChild(new XMLElement('h4', __('Override')));
			
			if (isset($override->data()->id)) {
				$wrapper->appendChild(Widget::Input(
					"{$prefix}[id]",
					$override->data()->id, 'hidden'
				));
			}
			
			// Expression
			$label = Widget::Label(__('Expression'));
			$label->appendChild(Widget::Input(
				"{$prefix}[expression]",
				General::sanitize(
					isset($override->data()->expression)
						? $override->data()->expression
						: null
				)
			));
			
			if (isset($override->errors()->expression)) {
				$label = Widget::wrapFormElementWithError($label, $override->errors()->expression);
			}
			
			$wrapper->appendChild($label);
			
			// Subject
			$fieldset = new XMLElement('fieldset');
			$fieldset->appendChild(new XMLElement('legend', __('Content')));
			
			$label = Widget::Label(__('Subject'));
			$label->appendChild(Widget::Input(
				"{$prefix}[subject]",
				General::sanitize(
					isset($override->data()->subject)
						? $override->data()->subject
						: null
				)
			));
			
			if (isset($override->errors()->subject)) {
				$label = Widget::wrapFormElementWithError($label, $override->errors()->subject);
			}
			
			$fieldset->appendChild($label);
			
			// Sender Name
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Sender Name'));
			$label->appendChild(Widget::Input(
				"{$prefix}[sender_name]",
				General::sanitize(
					isset($override->data()->sender_name)
						? $override->data()->sender_name
						: null
				)
			));
			
			if (isset($override->errors()->sender_name)) {
				$label = Widget::wrapFormElementWithError($label, $override->errors()->sender_name);
			}
			
			$group->appendChild($label);
			
			// Senders
			$label = Widget::Label(__('Sender Address'));
			$label->appendChild(Widget::Input(
				"{$prefix}[sender_address]",
				General::sanitize(
					isset($override->data()->sender_address)
						? $override->data()->sender_address
						: null
				)
			));
			
			if (isset($override->errors()->sender_address)) {
				$label = Widget::wrapFormElementWithError($label, $override->errors()->sender_address);
			}
			
			$group->appendChild($label);
			$fieldset->appendChild($group);
			
			// Recipients
			$label = Widget::Label(__('Recipient Address'));
			$label->appendChild(Widget::Input(
				"{$prefix}[recipient_address]",
				General::sanitize(
					isset($override->data()->recipient_address)
						? $override->data()->recipient_address
						: null
				)
			));
			
			if (isset($override->errors()->recipient_address)) {
				$label = Widget::wrapFormElementWithError($label, $override->errors()->recipient_address);
			}
			
			$fieldset->appendChild($label);
			$wrapper->appendChild($fieldset);
			
			// Page
			$this->appendTemplateFieldset($override, $wrapper, $prefix);
		}
		
		public function appendTemplateFieldset($email, $wrapper, $prefix = 'fields') {
			$fieldset = new XMLElement('fieldset');
			$fieldset->appendChild(new XMLElement('legend', __('Template')));
			
			if ($prefix == 'fields') {
				$fieldset->setAttribute('class', 'settings');
				
				$help = new XMLElement('p');
				$help->setAttribute('class', 'help');
				$help->setValue(__('The <code>%s</code> parameter can be used by any datasources on your template page.', array('$etf-entry-id')));
				
				$fieldset->appendChild($help);
			}
			
			// Page:
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Page'));
			$options = array(
				array(null, false, __('Choose one...'))
			);
			
			foreach ($this->getPages() as $page) {
				$selected = ($page->id == $email->data()->page_id);
				$options[] = array(
					$page->id, $selected, $page->path
				);
			}
			
			$select = Widget::Select(
				"{$prefix}[page_id]", $options
			);
			$select->setAttribute('class', 'page-picker');
			$label->appendChild($select);
			
			if (isset($email->errors()->page_id)) {
				$label = Widget::wrapFormElementWithError($label, $email->errors()->page_id);
			}
			
			$div->appendChild($label);
			$fieldset->appendChild($div);
			$wrapper->appendChild($fieldset);
		}
		
		public function getPages() {
			$pages = Symphony::Database()->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				ORDER BY
					`sortorder` ASC
			");
			$result = array();
			
			foreach ($pages as $page) {
				$page = (object)$page;
				$path = '';
				
				if ($page->path) {
					$path = '/' . $page->path;
				}
				
				$path .= '/' . $page->handle;
				
				$result[] = (object)array(
					'id'	=> $page->id,
					'path'	=> $path
				);
			}
			
			sort($result);
			
			return $result;
		}
	}
	
?>