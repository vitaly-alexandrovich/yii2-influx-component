<?php namespace yii\components;

use InfluxDB\Client\Exception;
use InfluxDB\Database;
use yii\base\Component;
use yii\base\Event;
use yii\web\Application;
use InfluxDB\Point;

class InfluxComponent extends Component
{
	public $username;
	public $password;
	public $host;
	public $port;
	public $databaseName;

	private $points = [];

	private $ready = null;

	/**
	 * @var Database $database
	 */
	private $database = null;

	/**
	 * InfluxMetricsComponent constructor.
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		parent::__construct($config);
	}

	/**
	 * initialize component
	 */
	public function init()
	{
		try {
			/** @noinspection SpellCheckingInspection */
			$this->database = \InfluxDB\Client::fromDSN(
				sprintf(
					'influxdb://%s:%s@%s:%s/%s',
					$this->username,
					$this->password,
					$this->host,
					$this->port,
					$this->databaseName
				)
			);

			$this->ready = true;
		} catch (Exception $exception) {
			\Yii::error($exception->getMessage(), 'InfluxMetricsComponent');
			$this->ready = false;
			return;
		}

		Event::on(Application::class, Application::EVENT_AFTER_REQUEST, function () {
			$this->send();
		});
	}

	/**
	 * @param $name
	 * @param null $value
	 * @param $tags
	 * @param $fields
	 * @param null $timestamp
	 * @throws Database\Exception
	 */
	public function track($name, $value = null, $tags = [], $fields = [], $timestamp = null)
	{
		$this->points[] = new Point(
			$name,
			$value,
			$tags,
			$fields,
            $timestamp
		);
	}

	/**
	 * @return bool
	 */
	public function send()
	{
		if (empty($this->points)) {
			return true;
		}

		if ($this->ready === false) {
			return false;
		}

		try {
			return $this
				->database
				->writePoints($this->points, Database::PRECISION_NANOSECONDS);

		} catch (\InfluxDB\Exception $exception) {
			\Yii::error($exception->getMessage(), 'InfluxMetricsComponent');
			return false;
		}
	}
}