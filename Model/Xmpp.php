<?php
/**
 * Created by JetBrains PhpStorm.
 * User: spies
 * Date: 31.03.12 (13 KW)
 * Time: 15:18
 * To change this template use File | Settings | File Templates.
 */
require_once BP . DS . 'lib' . DS  . 'XMPPHP' . DS . 'XMPP.php';

class Hackathon_Logger_Model_Xmpp extends Zend_Log_Writer_Abstract
{
	/**
	 * Array of formatted events to include in message body.
	 *
	 * @var array
	 */
	protected $_eventsToSend = array();

	/**
	 * Array of xmpp connection information. default to gtalk/gmail info
	 *
	 * @var array
	 */
	public $options = array(
		'host' => '',
		'port' => 5222,
		'user' => '',
		'password' => '',
		'resource' => '',
		'server' => '',
		'recipient' => '');

	/**
	 * @param array $options xmpp connection information, mandatory: user, password, recipient
	 * @return void
	 */
	public function __construct($filename)
	{
		$this->setFormatter(new Zend_Log_Formatter_Simple());
	}

	/**
	 * Construct a Zend_Log driver for xmpp servers
	 *
	 * @param  array|Zend_Config $config
	 * @return Zend_Log_FactoryInterface
	 */
	static public function factory($config)
	{
		$config = self::_parseConfig($config);
		if (!isset($config['remoteIP'])) {
			throw new InvalidArgumentException();
		}

		$instance = new self($config['remoteIP']);

		foreach ($config as $key => $value) {
			if (method_exists($instance, 'set' . ucfirst($key))) {
				$instance->{'set' . ucfirst($key)}($value);
			}
		}

		return $instance;
	}

	/**
	 * Places event line into array of lines to be used as message body.
	 *
	 *
	 * @param  array $event Event data
	 * @return void
	 */
	protected function _write($event)
	{
		$formattedEvent = $this->_formatter->format($event);

		$this->_eventsToSend[] = $formattedEvent;
	}


	/**
	 * Sends message recipient if log entries are present.
	 *
	 * @return void
	 */
	public function shutdown()
	{
		// If there are events to send, use them as message body.
		// Otherwise, there is no message to be sent.
		if (empty($this->_eventsToSend)) {
			return;
		}

		$events = implode('', $this->_eventsToSend);

		// Finally, send the IM, but re-throw any exceptions at the
		// proper level of abstraction.
		try {
			$conn = new XMPPHP_XMPP($this->options['host'],
				$this->options['port'],
				$this->options['user'],
				$this->options['password'],
				$this->options['resource'],
				$this->options['server'], true, XMPPHP_Log::LEVEL_DEBUG);

			$conn->connect();
			$conn->processUntil('session_start');
			$conn->presence();
			$conn->message($this->options['recipient'], $events);
			$conn->disconnect();
		} catch (Exception $e) {
			throw new Zend_Log_Exception(
				$e->getMessage(),
				$e->getCode());
		}
	}
}