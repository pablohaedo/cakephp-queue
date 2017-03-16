<?php
namespace Queue\Model\Entity;

class MongoQueuedJob {
	private $_id = null;
	private $job_type;
	private $data = null;
	private $job_group = null;
	private $reference = null;
	private $created = null;
	private $notbefore = null;
	private $fetched = null;
	private $completed = null;
	private $progress = null;
	private $failed;
	private $failure_message = null;
	private $workerkey = null;
	private $status = null;
	private $priority;

	public function __construct($job_type, $data, $priority) {
		$this->job_type = $job_type;
		$this->data = $data;
		$this->priority = $priority;
		$this->failed = 0;
	}

	public static function fromArray(Array $a) {
		$queueJob = new MongoQueuedJob($a['job_type'], $a['data'], 
										(array_key_exists('priority', $a) ? $a['priority'] : 5));

		if (array_key_exists('data', $a)) {
			$queueJob->setData($a['data']);
		}
		if (array_key_exists('job_group', $a)) {
			$queueJob->setJobGroup($a['job_group']);
		}
		if (array_key_exists('reference', $a)) {
			$queueJob->setReference($a['reference']);
		}

		if (array_key_exists('created', $a)) {
			$queueJob->setCreated(\MongoDB\BSON\UTCDateTime::unserialize($a['created']));
		} else {
			$queueJob->setCreated(new \MongoDB\BSON\UTCDateTime());
		}

		if (array_key_exists('notbefore', $a)) {
			$queueJob->setNotBefore($a['notbefore']);
		}
		if (array_key_exists('fetched', $a)) {
			$queueJob->setFetched($a['fetched']);
		}
		if (array_key_exists('completed', $a)) {
			$queueJob->setCompleted($a['completed']);
		}
		if (array_key_exists('progress', $a)) {
			$queueJob->setProgress($a['progress']);
		}
		if (array_key_exists('failure_message', $a)) {
			$queueJob->setFailureMessage($a['failure_message']);
		}
		if (array_key_exists('worker_key', $a)) {
			$queueJob->setWorkerKey($a['worker_key']);
		}
		if (array_key_exists('status', $a)) {
			$queueJob->setStatus($a['status']);
		}
	}

	public function getId() {
		return $this->$_id;
	}
	public function setId($_id) {
		$this->_id = $_id;
	}

	public function getJobType() {
		return $this->$job_type;
	}
	public function setJobType($job_type) {
		$this->job_type = $job_type;
	}

	public function getData() {
		return $this->$data;
	}
	public function setData($data) {
		$this->data = $data;
	}

	public function getJobGroup() {
		return $this->$job_group;
	}
	public function setJobGroup($job_group) {
		$this->job_group = $job_group;
	}

	public function getReference() {
		return $this->$reference;
	}
	public function setReference($reference) {
		$this->reference = $reference;
	}

	public function getCreated() {
		return $this->$created;
	}
	public function setCreated($created) {
		$this->created = $created;
	}

	public function getNotBefore() {
		return $this->$notbefore;
	}
	public function setNotBefore($notbefore) {
		$this->notbefore = $notbefore;
	}

	public function getFetched() {
		return $this->$fetched;
	}
	public function setFetched($fetched) {
		$this->fetched = $fetched;
	}

	public function getCompleted() {
		return $this->$completed;
	}
	public function setCompleted($completed) {
		$this->completed = $completed;
	}

	public function getProgress() {
		return $this->$progress;
	}
	public function setProgress($progress) {
		$this->progress = $progress;
	}

	public function getFailed() {
		return $this->$failed;
	}
	public function setFailed($failed) {
		$this->failed = $failed;
	}
	public function increaseFailed() {
		$this->failed++;
	}

	public function getFailureMessage() {
		return $this->$failure_message;
	}
	public function setFailureMessage($failure_message) {
		$this->failure_message = $failure_message;
	}

	public function getWorkerKey() {
		return $this->$workerkey;
	}
	public function setWorkerKey($workerkey) {
		$this->workerkey = $workerkey;
	}

	public function getStatus() {
		return $this->$status;
	}
	public function setStatus($status) {
		$this->status = $status;
	}

	public function getPriority() {
		return $this->$priority;
	}
	public function setPriority($priority) {
		$this->priority = $priority;
	}

	public function errors() {
		return false;
	}
}

