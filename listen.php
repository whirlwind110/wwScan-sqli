<?php
/*error_reporting(0);*/

class proxy {
	public $host;
	public $port;
	public $ssl;
	function __construct($host = "127.0.0.1", $port = "9999", $ssl = 0) {
		$this->host = $host;
		$this->port = $port;
		$this->ssl = $ssl;
	}
	function run() {
		if ($this->ssl) {
			$serv = new swoole_server($this->host, $this->port, SWOOLE_BASE, SWOOLE_SOCK_TCP | SWOOLE_SSL);
			$serv->set(array(
				'ssl_cert_file' => __DIR__ . '/ca/ssl.crt',
				'ssl_key_file' => __DIR__ . '/ca//ssl.key',
			));
		} else {
			$serv = new swoole_server($this->host, $this->port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
		}

		$serv->set(array(
			'reactor_num' => 8, //reactor thread num
			'worker_num' => 32, //worker process num
			'backlog' => 128, //listen backlog
			'max_request' => 100,
			'dispatch_mode' => 1,
			'max_conn' => 1024, //最大链接
			//'daemonize' => 0, //后台进程守护
			'open_cpu_affinity' => 1, //启用CPU亲和设置
			'log_file' => './swoole.log',
		));
		$serv->on('Start', array($this, 'onStart'));
		$serv->on('Connect', array($this, 'onConnect'));
		$serv->on('Receive', array($this, 'onReceive'));
		$serv->on('Close', array($this, 'onClose'));
		$serv->start();
	}
	function onStart($server) {
		echo $this->host . ":" . $this->port . " ssl=" . $this->ssl . " Server 启动。\nmaster_pid:" . $server->master_pid . "\n";
	}
	function onConnect($server, $fd) {

	}
	function onReceive($server, $fd, $from_id, $data) {
		$data = preg_replace("/keep-alive/i", "close", $data);
		//echo $data;
		preg_match("/.*/", $data, $head);
		$head = explode(" ", $head[0]);
		//ssl check
		if (strcasecmp(trim($head[0]), "CONNECT") === 0) {
			$host = explode(":", $head[1]);
			$ip = trim($host[0]);
			$port = empty($host[1]) ? "443" : trim($host[1]);
			$data = preg_replace("/CONNECT/i", "GET", $data);
			$client = new swoole_client(SWOOLE_SOCK_TCP | SWOOLE_SSL);
		} else {
			preg_match("/(Host: )(.*)/i", $data, $matche);
			$matche = explode(":", $matche[2]);
			$ip = trim($matche[0]);
			$port = empty($matche[1]) ? "80" : trim($matche[1]);
			$client = new swoole_client(SWOOLE_SOCK_TCP);
		}
		if ($client->connect($ip, $port, 3)) {
			$client->send($data);
			$res = "";
			do {
				$server->send($fd, $client->recv());
			} while (@$client->recv(65535, swoole_client::MSG_PEEK));
			echo $ip . ":" . $port . "发送完毕\n";
		}
		unset($client);
		$server->close($fd);
	}

	function onClose($server) {
	}
}
