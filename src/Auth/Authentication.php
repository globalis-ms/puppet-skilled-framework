<?php
namespace Globalis\PuppetSkilled\Auth;

use \Globalis\PuppetSkilled\Core\Application;
use \Globalis\PuppetSkilled\Library\FormValidation;

class Authentication extends \Globalis\PuppetSkilled\Service\Base
{
    protected $userSessionKey = 'authentication.user';

    protected $permissionsSessionKey = 'authentication.capabilities';

    protected $resources = [];

    protected $user;

    public function newUserTable()
    {
        return new \App\Model\User();
    }

    /**
     * Add Resource
     *
     * @param string $resource Resource class name
     */
    public function addResource($resource)
    {
        $this->resources[] = $resource;
        return $this;
    }

    public function login(array $data = null)
    {
        $validator = new FormValidation();
        $validator->set_rules(
            'username',
            'lang:authentication_label_username',
            [
                'trim',
                [
                    'authentication_error_invalid_account',
                    function ($value) use ($validator) {
                        $password = $validator->set_value('password');
                        if (empty($value)) {
                            return true;
                        }
                        $userModel = $this->newUserTable();
                        $user = $userModel->where('username', $value)->first();
                        if ($user && $user->verifyPassword($password)) {
                            $validator->validation_data['userEntity'] = $user;
                            return true;
                        }
                        return false;
                    }
                ],
                'required'
            ]
        );
        $validator->set_rules(
            'password',
            'lang:authentication_label_password',
            [
                'trim',
                'required'
            ]
        );
        if ($validator->run($data)) {
            $this->loadProfil($validator->validation_data['userEntity']);
        } else {
            if ($validator->ran() && $delay = $this->settings->get('authentication.delay_after_login_failed')) {
                sleep($delay);
            }
        }
        return $validator;
    }

    protected function loadProfil($user)
    {
        $acl = new Acl();
        foreach ($user->roles as $role) {
            // prepare resources
            $resources = [];
            if ($role->resources_support) {
                $resources_support = $role->resources_support;
                foreach ($resources_support as $resource) {
                    $resource = new $resource();
                    $resource->buildFromUserHasRoleid($role->pivot->getKey());
                    $resources[] = $resource;
                }
            } else {
                // No resource support, support all with all access
                foreach ($this->resources as $resource) {
                    $resource = new $resource();
                    $resource->acceptAll();
                    $resources[] = $resource;
                }
            }
            foreach ($role->permissions as $permission) {
                $acl->allow($permission->permission_name, $resources);
            }
        }
        $_SESSION[$this->userSessionKey] = $user->getKey();
        $_SESSION[$this->permissionsSessionKey] = $acl;
    }

    public function logout()
    {
        // Destruction de la session
        $this->session->sess_destroy();
        return true;
    }

    public function userCan($permission)
    {
        if ($currentPermissions = $this->permissions()) {
            return $currentPermissions->isAllowed($permission);
        }
        return false;
    }

    public function permissions()
    {
        return $this->session->{$this->permissionsSessionKey};
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->session->{$this->userSessionKey}) {
            // Build user
            $this->user = $this->newUserTable()->find($this->session->{$this->userSessionKey});
            return $this->user;
        }
        return false;
    }

    public function isLoggedIn()
    {
        return ($this->user() ?  true : false);
    }

    public function getResources()
    {
        return $this->resources;
    }
}
