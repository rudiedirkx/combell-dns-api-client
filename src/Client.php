<?php

namespace rdx\combelldns;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RedirectMiddleware;

class Client {

	public $auth; // rdx\combelldns\WebAuth
	public $guzzle; // GuzzleHttp\Client
	public $domains = [];
	public $csrfToken = '';

	/**
	 * Dependency constructor
	 */
	public function __construct( WebAuth $auth ) {
		$this->auth = $auth;

		$this->setUpGuzzle();
	}

	/**
	 *
	 */
	public function deleteDnsRecord( Domain $domain, DnsRecord $record ) {
		$type = strtolower($record->type);

		$data = [
			'submit' => 'Toevoegen',
			'csrf_token' => $this->csrfToken,
		];
		switch ( $type ) {
			case 'txt':
				$data += [
					'action' => 'deletetxt',
					'recordid' => $record->id,
				];
				break;

			case 'a':
				$data += [
					'action' => 'deletea',
					'recordid' => $record->id,
				];
				break;

			default:
				return false;
		}

// print_r($data);
		$rsp = $this->guzzle->request('POST', "product/dns/record/$type/$domain->id/1///50", [
			'form_params' => $data,
			'headers' => [
				'Accept' => 'application/json, text/javascript',
				'X-Requested-With' => 'XMLHttpRequest',
			],
		]);
// echo "\n\ndelete:\n\n" . $rsp->getBody() . "\n\n\n\n";

		return $rsp->getStatusCode() == 200;
	}

	/**
	 *
	 */
	public function addDnsRecord( Domain $domain, DnsRecord $record ) {
		$name = preg_replace('#\.' . preg_quote($domain->name, '#') . '$#', '', $record->name);
		if ( $name == $record->name ) {
			return false;
		}

		$type = strtolower($record->type);

		$data = [
			'submit' => 'Toevoegen',
			'csrf_token' => $this->csrfToken,
		];
		switch ( $type ) {
			case 'txt':
				$data += [
					'action' => 'addtxt',
					'txt_add_hostname' => $name,
					'txt_add_content' => $record->value,
					'txt_add_ttl' => $record->ttl,
				];
				break;

			case 'a':
				$data += [
					'action' => 'adda',
					'a_add_hostname' => $name,
					'a_add_destination' => $record->value,
					'a_add_ttl' => $record->ttl,
				];
				break;

			default:
				return false;
		}

// print_r($data);
		$rsp = $this->guzzle->request('POST', "product/dns/record/$type/$domain->id/1///50", [
			'form_params' => $data,
			'headers' => [
				'Accept' => 'application/json, text/javascript',
				'X-Requested-With' => 'XMLHttpRequest',
			],
		]);
// echo "\n\nadd:\n\n" . $rsp->getBody() . "\n\n\n\n";

		return $rsp->getStatusCode() == 200;
	}

	/**
	 *
	 */
	public function findDnsRecords( Domain $domain, $type, array $conditions ) {
		$records = @$domain->records[$type] ?: $this->getDnsRecords($domain, $type);

		return array_filter($records, function(DnsRecord $record) use ($conditions) {
			foreach ( $conditions as $prop => $propValue ) {
				if ( $record->$prop != $propValue ) {
					return false;
				}
			}
			return true;
		});
	}

	/**
	 *
	 */
	public function getDnsRecords( Domain $domain, $type ) {
		$type = strtolower($type);

		$rsp = $this->guzzle->request('GET', "product/dns/record/$type/$domain->id/1///50");
		$html = (string) $rsp->getBody();

		$this->scrapeCsrfToken($html);

		return $domain->records[$type] = $this->scrapeDnsRecords($html, $type);
	}

	/**
	 *
	 */
	protected function scrapeDnsRecords( $html, $type ) {
		$doc = JsDomNode::create($html);
		$rows = $doc->queryAll('ul.settings > li.edit_row');

		$records = [];
		foreach ( $rows as $row ) {
			$cell = $row->query('.data.title');
			if ( !$cell ) break;

			$name = $cell->textContent;

			$id = $row->query('input[name="recordid"]')['value'];

			$cell = $cell->nextElementSibling;
			$input = $cell->query('input') ?: $cell->query('textarea');
			$value = $input['value'] ?: $input->textContent;

			$cell = $cell->nextElementSibling;
			$ttl = $cell->query('input')['value'];

			$records[] = new DnsRecord($id, $name, $type, $value, $ttl);
		}

		return $records;
	}

	/**
	 *
	 */
	public function getDomain( $wanted ) {
		$domains = $this->domains ?: $this->getDomains();
		return array_reduce($domains, function($result, Domain $domain) use ($wanted) {
			return $domain->name === $wanted ? $domain : $result;
		}, null);
	}

	/**
	 *
	 */
	public function getDomains() {
		$rsp = $this->guzzle->request('GET', 'product/dns');
		$html = (string) $rsp->getBody();

		return $this->domains = $this->scrapeDomains($html);
	}

	/**
	 *
	 */
	protected function scrapeCsrfToken( $html ) {
		$doc = JsDomNode::create($html);

		$this->csrfToken = $doc->query('meta[name="csrf_token"]')['content'];

		return (bool) $this->csrfToken;
	}

	/**
	 *
	 */
	protected function scrapeDomains( $html ) {
		$doc = JsDomNode::create($html);
		$rows = $doc->queryAll('.table-responsive tbody tr');

		$domains = [];
		foreach ( $rows as $row ) {
			if ( preg_match('#/product/dns/overview/([^/"\']+)#', $row->innerHTML, $match) ) {
				$id = $match[1];
				$domain = $row->child(':first-child')->textContent;

				$domains[] = new Domain($id, $domain);
			}
		}

		return $domains;
	}

	/**
	 *
	 */
	public function logIn() {
		$rsp = $this->guzzle->request('GET', '');

		$html = (string) $rsp->getBody();
		$doc = JsDomNode::create($html);

		$oauth = !!$doc->query('#ReturnUrl');
		return $oauth ? $this->logInOauth($rsp, $doc) : $this->logInSimple($rsp, $doc);
	}

	/**
	 *
	 */
	protected function logInOauth( Response $rsp, JsDomNode $doc ) {
		// throw new \Exception("Oauth login not supported =(");

		$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		$currentUrl = end($redirects);

		$returnUrl = $doc->getFormValue('ReturnUrl');
		$token = $doc->getFormValue('__RequestVerificationToken');
		$originDesc = $doc->getFormValue('ReturnOriginDescription');

		$rsp = $this->guzzle->request('POST', $currentUrl, [
			'form_params' => [
				'ReturnUrl' => $returnUrl,
				'Email' => $this->auth->user,
				'Password' => $this->auth->pass,
				'__RequestVerificationToken' => $token,
				'ReturnOriginDescription' => $originDesc,
				'button' => 'login',
				'RememberLogin' => 'false',
			],
		]);

		$html = (string) $rsp->getBody();
		$doc = JsDomNode::create($html);

		$form = $doc->query('form');
		$values = $form->getFormValues();

		$rsp = $this->guzzle->request('POST', $form['action'], [
			'form_params' => $values,
		]);

		$html = (string) $rsp->getBody();
		if ( strpos($html, 'login-panel-logged-in') !== false ) {
			return true;
		}

		return false;
	}

	/**
	 *
	 */
	protected function logInSimple( Response $rsp, JsDomNode $doc ) {
		$csrfToken = $doc->query('meta[name="csrf_token"]')['content'];
		$form = $doc->query('#form_login');
		$language = $form->query('input[name="language"]')['value'];
		$submit = $form->query('input[name="submit"]')['value'];

		$rsp = $this->guzzle->request('POST', 'login', [
			'form_params' => [
				'language' => $language,
				'login' => $this->auth->user,
				'pass' => $this->auth->pass,
				'submit' => $submit,
				'csrf_token' => $csrfToken,
			],
		]);

		if ( $rsp->getStatusCode() == 200 ) {
			$rsp = $this->guzzle->request('GET', 'home');

			$html = (string) $rsp->getBody();
			$this->scrapeCsrfToken($html);

			// @todo Extract Customer?

			return true;
		}

		return false;
	}

	/**
	 *
	 */
	protected function setUpGuzzle() {
		$cookies = $this->auth->cookies;
		$stack = HandlerStack::create();
		$this->guzzle = new Guzzle([
			'base_uri' => $this->auth->base,
			'http_errors' => false,
			'handler' => $stack,
			'cookies' => $cookies,
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);

		$this->setUpLog($stack);
	}

	/**
	 *
	 */
	protected function setUpLog( HandlerStack $stack ) {
		$stack->push(Middleware::tap(
			function(Request $request, $options) {
				$this->guzzle->log[] = [
					'time' => microtime(1),
					'request' => strtoupper($request->getMethod()) . ' ' . $request->getUri(),
				];
			},
			function(Request $request, $options, PromiseInterface $response) {
				$response->then(function(Response $response) {
					$log = &$this->guzzle->log[count($this->guzzle->log) - 1];
					$log['time'] = microtime(1) - $log['time'];
					$log['response'] = $response->getStatusCode();
				});
			}
		));

		$this->guzzle->log = [];
	}

}
