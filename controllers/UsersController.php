<?php
/**
 * Slicedup: a fancy tag line here
 *
 * @copyright	Copyright 2010, Paul Webster / Slicedup (http://slicedup.org)
 * @license 	http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace sli_users\controllers;

use lithium\core\Libraries;
use lithium\net\http\Media;
use lithium\util\Set;
use sli_util\action\FlashMessage;
use sli_libs\core\LibraryRegistry;
use sli_users\security\CurrentUser;

/**
 * The `UsersController` class is the base controller used for all actions of the sli_users
 * library, it should be extended as required to alter & add actions.
 */
class UsersController extends \lithium\action\Controller {

	/**
	 * Runtime config set on __invoke
	 *
	 * @var array
	 */
	public $runtime;

	/**
	 * Init to set __invoke filter to load runtime config
	 */
	protected function _init(){
		parent::_init();
		$this->applyFilter('__invoke', function($self, $params, $chain) {
			if (isset($self->request->params['config'])) {
				$self->runtime = LibraryRegistry::current('sli_users', $self->request->params['config']);
			}
			$base = LibraryRegistry::base('sli_users.controller.actions');
			if (!isset($self->runtime) && !empty($base[$self->request->action])) {
				$class = get_class($self);
				throw new \RuntimeException("{$class}::{$self->request->action} cannot be run without a runtime configuration.");
			}

			$library = Libraries::get('sli_users');
			$htmlOptions = array(
				'view' => '\lithium\template\View',
				'paths' => array(
					'template' => array(
						'{:library}/views/{:controller}/{:template}.{:type}.php',
						LITHIUM_APP_PATH . '/views/{:controller}/{:template}.{:type}.php',
						$library['path'] . '/views/{:controller}/{:template}.{:type}.php',
					),
					'layout' => array(
						'{:library}/views/layouts/{:layout}.{:type}.php',
						LITHIUM_APP_PATH . '/views/layouts/{:layout}.{:type}.php',
						$library['path'] . '/views/layouts/{:layout}.{:type}.php'
					),
					'element' => array(
						'{:library}/views/elements/{:template}.{:type}.php',
					)
				)
			);
			$html = Media::type('html');
			Media::type('html', $html['content'], Set::merge($html['options'], $htmlOptions));
			return $chain->next($self, $params, $chain);
		});
	}

	/**
	 * Login action
	 */
	public function login() {
		$configName = $this->request->params['config'];
		if (isset($this->request->query['return'])) {
			CurrentUser::actionReturn($configName, 'login', $this->request->query['return']);
		}
		$persist = ($this->runtime['persist'] && isset($this->request->data['remember_me']));
		if ($user = CurrentUser::login($configName, $this->request, compact('persist'))) {
			$this->redirect(CurrentUser::actionReturn($configName, 'login', false));
		}
		if (isset($this->runtime['template']['login']['fields'])) {
			$fields = $this->runtime['template']['login']['fields'];
		} elseif (isset($this->runtime['auth']['options']['fields'])) {
			$fields = (array) $this->runtime['auth']['options']['fields'];
		} else {
			$fields = array('username', $this->runtime['model']['password']);
		}

		if (!empty($this->request->data)) {
			FlashMessage::error('Login failed, please check your details!');
		}

		$persist = (boolean) $this->runtime['persist'];
		$passwordReset = (boolean) $this->runtime['controller']['actions']['password_reset'];
		$register = (boolean) $this->runtime['controller']['actions']['register'];
		$this->set(compact('status', 'persist', 'register', 'passwordReset', 'fields'));
	}

	/**
	 * Logout action
	 */
	public function logout() {
		$configName = $this->request->params['config'];
		if (isset($this->request->query['return'])) {
			CurrentUser::actionReturn($configName, 'logout', $this->request->query['return']);
		}
		CurrentUser::logout($configName);
		FlashMessage::success('You have been logged out.');
		$this->redirect(CurrentUser::actionReturn($configName, 'logout', false));
	}

	/**
	 * Register action
	 *
	 * @todo registration processing
	 */
	public function register() {
		$status = '';
		if ($this->request->data) {
			$status = 'error';
		}
		if (isset($this->runtime['template']['register']['fields'])) {
			$fields = $this->runtime['template']['register']['fields'];
		} else {
			$model = $this->runtime['model']['class'];
			$schema = $model::schema();
			$key = $model::key();
			unset($schema[$key]);
			$fields = array_keys((array) $schema);
		}
		$this->set(compact('status', 'fields'));
	}

	/**
	 * Password Reset Action
	 *
	 * @todo password reset processing
	 */
	public function password_reset() {
		$status = '';
		if ($this->request->data) {
			$status = 'error';
		}
		if (isset($this->runtime['template']['password_reset']['fields'])) {
			$fields = $this->runtime['template']['password_reset']['fields'];
		} else {
			$fields = array('email');
		}
		$this->set(compact('status', 'fields'));
	}
}