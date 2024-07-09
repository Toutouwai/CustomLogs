<?php namespace ProcessWire;

class CustomLogs extends WireData implements Module, ConfigurableModule {

	/**
	 * Construct
	 */
	public function __construct() {
		parent::__construct();
		$this->customLogsParsed = [];
	}

	/**
	 * Ready
	 */
	public function ready() {
		$this->addHookBefore('ProcessLogger::executeView', $this, 'beforeViewLog');
		$this->addHookBefore('Modules::saveConfig', $this, 'beforeSaveConfig');
	}

	/**
	 * Before ProcessLogger::executeView
	 * Mostly copied from ProcessLogger::executeView() with changes marked "CustomLogs mod"
	 *
	 * @param HookEvent $event
	 */
	protected function beforeViewLog(HookEvent $event) {
		$input = $this->wire()->input;
		$session = $this->wire()->session;
		$config = $this->wire()->config;
		$modules = $this->wire()->modules;
		$sanitizer = $this->wire()->sanitizer;
		$log = $this->wire()->log;

		$name = $input->urlSegment2;
		if(!$name) $session->redirect('../');

		// Return early if this log is not configured as a custom log
		$custom_logs = $this->customLogsParsed;
		if(!isset($custom_logs[$name])) return;

		$event->replace = true;
		/** @var ProcessLogger $pl */
		$pl = $event->object;

		//<editor-fold desc="Copied from ProcessLogger::executeView()">
		$logs = $log->getLogs();
		if(!isset($logs[$name])) {
			$this->error(sprintf('Unknown log: %s', $name));
			$session->location('../');
		}
		$action = $input->post('action');
		if($action) $pl->processAction($action, $name); // CustomLogs mod
		$limit = 100;
		$options = array('limit' => $limit);

		$q = $input->get('q');
		if($q !== null && strlen($q)) {
			$options['text'] = $sanitizer->text($q);
			$input->whitelist('q', $options['text']);
		}

		$dateFrom = $input->get('date_from');
		if($dateFrom !== null && strlen($dateFrom)) {
			$options['dateFrom'] = ctype_digit("$dateFrom") ? (int) $dateFrom : strtotime("$dateFrom 00:00:00");
			$input->whitelist('date_from', $options['dateFrom']);
		}

		$dateTo = $input->get('date_to');
		if($dateTo !== null && strlen($dateTo)) {
			$options['dateTo'] = ctype_digit("$dateTo") ? (int) $dateTo : strtotime("$dateTo 23:59:59");
			$input->whitelist('date_to', $options['dateTo']);
		}

		$options['pageNum'] = (int) $input->pageNum;

		do {
			// since the total count the pagination is based on may not always be accurate (dups, etc.)
			// we migrate to the last populated pagination when items turn up empty
			$items = $this->getEntries($name, $options);
			if(count($items)) break;
			if($options['pageNum'] < 2) break;
			$options['pageNum']--;
		} while(1);

		// CustomLogs mod
		if($config->ajax) {
			$event->return = $this->renderLogAjax($items, $name);
			return;
		}
//		if($config->ajax) return $this->renderLogAjax($items, $name);

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->attr('id', 'FieldsetTools');
		$fieldset->label = $this->_('Helpers');
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->icon = 'sun-o';
		$form->add($fieldset);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'q');
		$f->label = $this->_('Text Search');
		$f->icon = 'search';
		$f->columnWidth = 50;
		$fieldset->add($f);

		/** @var InputfieldDatetime $f */
		$f = $modules->get('InputfieldDatetime');
		$f->attr('name', 'date_from');
		$f->label = $this->_('Date From');
		$f->icon = 'calendar';
		$f->columnWidth = 25;
		$f->datepicker = InputfieldDatetime::datepickerFocus;
		$f->attr('placeholder', 'yyyy-mm-dd');
		$fieldset->add($f);

		/** @var InputfieldDatetime $f */
		$f = $modules->get('InputfieldDatetime');
		$f->attr('name', 'date_to');
		$f->icon = 'calendar';
		$f->label = $this->_('Date To');
		$f->columnWidth = 25;
		$f->attr('placeholder', 'yyyy-mm-dd');
		$f->datepicker = InputfieldDatetime::datepickerFocus;
		$fieldset->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'action');
		$f->label = $this->_('Actions');
		$f->description = $this->_('Select an action below. You will be asked to click a button before the action is executed.');
		$f->icon = 'fire';
		$f->collapsed = Inputfield::collapsedYes;
		$f->addOption('download', $this->_('Download'));
		$fieldset->add($f);

		if($this->wire()->user->hasPermission('logs-edit')) {

			// CustomLogs mod
//			$f->addOption('add', $this->_('Grow (Add Entry)'));
			$f->addOption('prune', $this->_('Chop (Prune)'));
			$f->addOption('delete', $this->_('Burn (Delete)'));

			/** @var InputfieldInteger $f */
			$f = $modules->get('InputfieldInteger');
			$f->attr('name', 'prune_days');
			$f->label = $this->_('Chop To # Days');
			$f->inputType = 'number';
			$f->min = 1;
			$f->icon = 'cut';
			$f->description = $this->_('Reduce the size of the log file to contain only entries from the last [n] days.');
			$f->notes = $this->_('Must be 1 or greater.');
			$f->value = 30;
			$f->showIf = "action=prune";
			$fieldset->add($f);

			/** @var InputfieldText $f */
			// CustomLogs mod
//			$f = $modules->get('InputfieldText');
//			$f->attr('name', 'add_text');
//			$f->label = $this->_('New Log Entry');
//			$f->icon = 'leaf';
//			$f->showIf = "action=add";
//			$fieldset->add($f);

			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
			$f->value = $this->_('Chop this log file now');
			$f->icon = 'cut';
			$f->attr('name', 'submit_prune');
			$f->showIf = 'action=prune';
			$fieldset->add($f);

			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
			$f->value = $this->_('Burn this log now (permanently delete)');
			$f->icon = 'fire';
			$f->attr('name', 'submit_delete');
			$f->showIf = 'action=delete';
			$fieldset->add($f);

			/** @var InputfieldSubmit $f */
			// CustomLogs mod
//			$f = $modules->get('InputfieldSubmit');
//			$f->value = $this->_('Add this log entry');
//			$f->icon = 'leaf';
//			$f->attr('name', 'submit_add');
//			$f->showIf = 'action=add';
//			$fieldset->add($f);
		}

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit');
		$f->value = $this->_('Download this log file now');
		$f->icon = 'download';
		$f->attr('name', 'submit_download');
		$f->showIf = 'action=download';
		$fieldset->add($f);

		$pl->headline(ucfirst($name)); // CustomLogs mod
		$pl->breadcrumb('../../', $this->wire()->page->title); // CustomLogs mod
		//</editor-fold>

		$event->return = $form->render() .
			"<div id='ProcessLogEntries'>" .
			$this->renderLog($items, $name) .
			"</div>";
	}

	/**
	 * Render log - AJAX request
	 * Copied from ProcessLogger::renderLogAjax() with changes marked "CustomLogs mod"
	 *
	 * @param array $items
	 * @param string $name
	 * @return string
	 */
	protected function renderLogAjax(array $items, $name) {
		$input = $this->wire()->input;

		$time = (int) $input->get('time');
		$render = true;
		$qtyNew = 0;
		$note = '';
		if($time) {
			foreach($items as $entry) {
				// CustomLogs mod
				$entryTime = strtotime($entry[0]);
//				$entryTime = strtotime($entry['date']);
				if($entryTime > $time) $qtyNew++;
			}
			if(!$qtyNew) $render = false;
		}
		if($qtyNew) {
			$note = sprintf($this->_n('One new log entry on page 1', 'Multiple new log entries on page 1', $qtyNew), $qtyNew);
			$note .= " (" . date('H:i:s') . ")";
		}
		$data = array(
			'qty' => -1,
			'qtyNew' => 0,
			'out' => '',
			'note' => $note,
			'time' => time(),
			'url' => $input->url() . '?' . $input->queryString()
		);
		if($render) {
			$data = array_merge($data, array(
				'qty' => count($items),
				'qtyNew' => $qtyNew,
				'out' => $this->renderLog($items, $name, $time),
			));
		} else {
			// leave default data, which tells it not to render anything
		}
		header("Content-type: application/json;");
		return json_encode($data);
	}

	/**
	 * Render log
	 * Some code copied from ProcessLogger::renderLog() with changes marked "CustomLogs mod"
	 *
	 * @param array $items
	 * @param string $name
	 * @param int $time
	 * @return string
	 */
	protected function renderLog(array $items, $name, $time = 0) {
		/** @var ProcessLogger $pl */
		$pl = $this->wire()->modules->get('ProcessLogger');
		$sanitizer = $this->wire()->sanitizer;

		$custom_logs = $this->customLogsParsed;

		/** @var MarkupAdminDataTable $table */
		$table = $this->wire()->modules->get('MarkupAdminDataTable');
		$table->setSortable(false);
		$table->setEncodeEntities(false);
		$headers = $custom_logs[$name];
		$url_cols = [];
		foreach($headers as $key => $header) {
			if(substr($header, 0, 5) === '{url}') {
				$url_cols[] = $key;
				$headers[$key] = substr($header, 5);
			}
		}
		array_unshift($headers, $this->_('Date'));
		$table->headerRow($headers);

		$leaf_icon = wireIconMarkup('leaf', 'ProcessLogNew');
		foreach($items as $entry) {
			$date = array_shift($entry);
			$ts = strtotime($date);
			$date_str = wireRelativeTimeStr($date);
			if($time && $ts > $time) $date_str = $leaf_icon . $date_str;
			$row = ["$date_str<br /><span class='detail'>$date</span>"];
			foreach($entry as $key => $value) {
				if($url_cols && in_array($key, $url_cols)) {
					$value = "<a href='$value'>$value</a>";
				} else {
					$value = $sanitizer->entities($value);
				}
				$row[] = $pl->formatLogText($value, $name);
			}
			$table->row($row);
		}

		//<editor-fold desc="Copied from ProcessLogger::renderLog()">
		/** @var LogEntriesArray $entries */
		$entries = $this->wire(new LogEntriesArray());

		if(count($items)) {
			reset($items);
			$key = key($items);
			list($n, $total, $start, $end, $limit) = explode('/', $key);
			// CustomLogs mod
//			if($n && $end) {} // ignore
			$entries->import($items);
			$entries->setLimit($limit);
			$entries->setStart($start);
			$entries->setTotal($total);
			/** @var MarkupPagerNav $pager */
			$pager = $this->wire()->modules->get('MarkupPagerNav');
			$options = array('baseUrl' => "../$name/");
			$pagerOut = $pager->render($entries, $options);
			$pagerHeadline = $entries->getPaginationString();
			$pagerHeadline .= " " .
				"<small class='ui-priority-secondary'>(" .
				($pager->isLastPage() ? $this->_('actual') : $this->_('estimate')) .
				")</small>";
			$iconClass = '';
		} else {
			$pagerHeadline = $this->_('No matching log entries');
			$iconClass = 'fa-rotate-270';
			$pagerOut = '';
		}

		$pageNum = $this->wire()->input->pageNum();
		$time = time();
		$spinner = wireIconMarkup('tree', "fw lf $iconClass id='ProcessLogPage'");

		$out =
			"<div id='ProcessLogPage' data-page='$pageNum' data-time='$time'>" .
			$pagerOut .
			"<h2 id='ProcessLogHeadline'>" .
			"$spinner $pagerHeadline " .
			"<small class='notes'></small></h2>" .
			$table->render() .
			"<div class='ui-helper-clearfix'>$pagerOut</div>" .
			"</div>";

		return $out;
		//</editor-fold>
	}

	/**
	 * Return given number of entries from end of log file, with each entry as an associative array of components
	 * Copied from WireLog::getEntries() with changes marked "CustomLogs mod"
	 *
	 * @param string $name
	 * @param array $options
	 * @return array
	 */
	protected function getEntries($name, array $options = array()) {
		// CustomLogs mod
		$log = $this->wire()->log->getFileLog($name);
//		$log = $this->getFileLog($name);
		$limit = isset($options['limit']) ? $options['limit'] : 100;
		$pageNum = !empty($options['pageNum']) ? $options['pageNum'] : $this->wire()->input->pageNum;
		unset($options['pageNum']);
		$lines = $log->find($limit, $pageNum, $options);

		foreach($lines as $key => $line) {
			$entry = $this->lineToEntry($line);
			$lines[$key] = $entry;
		}

		return $lines;
	}

	/**
	 * Convert a log line to an entry array
	 * This is a replacement for WireLog::lineToEntry()
	 *
	 * @param string $line
	 * @return array
	 */
	public function lineToEntry($line) {
		return explode("\t", $line);
	}

	/**
	 * Save data to a named log
	 *
	 * @param string $name
	 * @param array $data
	 * @param array $options options for FileLog::save()
	 *  - `allowDups` (bool): Allow duplicating same log entry in same runtime/request? (default=true)
	 *  - `mergeDups` (int): Merge previous duplicate entries that also appear near end of file?
	 *     To enable, specify int for quantity of bytes to consider from EOF, value of 1024 or higher (default=0, disabled)
	 *  - `maxTries` (int): If log entry fails to save, maximum times to re-try (default=20)
	 *  - `maxTriesDelay` (int): Micro seconds (millionths of a second) to delay between re-tries (default=2000)
	 * @return bool
	 */
	public function save($name, $data, $options = []) {
		$log = $this->wire()->log;
		$filelog = $log->getFileLog($name);
		foreach($data as $key => $value) {
			$data[$key] = str_replace(["\r", "\n", "\t"], ' ', $value);
		}
		$text = implode("\t", $data);
		return $filelog->save($text, $options);
	}

	/**
	 * Before Modules::saveConfig
	 *
	 * @param HookEvent $event
	 */
	protected function beforeSaveConfig(HookEvent $event) {
		// Only for this module's config data
		if($event->arguments(0) != $this) return;
		$data = $event->arguments(1);
		// Parse customLogs textarea value to array
		$parsed = [];
		$lines = explode("\n", str_replace("\r", "", $data['customLogs']));
		foreach($lines as $line) {
			$pieces = explode(':', $line, 2);
			if(count($pieces) !== 2) continue;
			$pieces = array_map('trim', $pieces);
			$column_heads = explode(',', $pieces[1]);
			$column_heads = array_map('trim', $column_heads);
			if(!$column_heads) continue;
			$parsed[$pieces[0]] = $column_heads;
		}
		$data['customLogsParsed'] = $parsed;
		$event->arguments(1, $data);
	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {
		$modules = $this->wire()->modules;

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f_name = 'customLogs';
		$f->name = $f_name;
		$f->label = $this->_('Custom logs');
		$f->description = $this->_('Enter custom logs, one per line, in the format "name: column label, column label, column label", with as many comma-separated column labels as needed. If you prefix a URL column label with {url} then the value in the column will be rendered as a link in the log viewer.');
		$f->description .= "\n" . $this->_('The date/time will automatically be added as the first column so you do not need to specify it here.');
		$f->description .= "\n" . $this->_('Example:');
		$f->description .= "\n" . $this->_('my-log: {url}URL, IP Address, User Agent, Details');
		$f->notes .= $this->_('Each log name must be a word consisting of only `[-._a-z0-9]` and no extension.');
		$f->rows = 3;
		$f->value = $this->$f_name;
		$inputfields->add($f);
	}

}
