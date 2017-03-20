<?php
/**
 * @author Andy Carter
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Queue\Shell\Task;

use Cake\Console\ConsoleIo;
use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;

use CakeMonga\MongoCollection\CollectionRegistry;

/**
 * Queue Task.
 *
 * Common Queue plugin tasks properties and methods to be extended by custom
 * tasks.
 */
class MongoQueueTask extends Shell {

	/**
	 * @var \Queue\Model\Table\QueuedJobsTable
	 */
	public $QueuedJobs;

	/**
	 * Timeout for run, after which the Task is reassigned to a new worker.
	 *
	 * @var int
	 */
	public $timeout = 120;

	/**
	 * Number of times a failed instance of this task should be restarted before giving up.
	 *
	 * @var int
	 */
	public $retries = 4;

	/**
	 * Stores any failure messages triggered during run()
	 *
	 * @var string|null
	 */
	public $failureMessage = null;

	/**
	 * @param \Cake\Console\ConsoleIo|null $io IO
	 */
	public function __construct(ConsoleIo $io = null) {
		parent::__construct($io);

		CollectionRegistry::setNamespace("Queue\\Model\\MongoCollection\\");
		// TODO Take connection name from configuration
		//$config = TableRegistry::exists('QueuedJobs') ? [] : ['className' => QueuedJobsTable::class];
		$this->QueuedJobs = CollectionRegistry::get('QueuedJobs', ['connection' => 'mongo_db']);
	}

	/**
	 * Add functionality.
	 *
	 * @return void
	 */
	public function add() {
	}

	/**
	 * Run function.
	 * This function is executed, when a worker is executing a task.
	 * The return parameter will determine, if the task will be marked completed, or be requeued.
	 *
	 * @param array $data The array passed to QueuedTask->createJob()
	 * @param int $id The id of the QueuedTask
	 * @return bool Success
	 */
	public function run(array $data, $id) {
		return true;
	}

}
