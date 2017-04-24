<?php
namespace Globalis\PuppetSkilled\Auth;

use \Globalis\PuppetSkilled\Core\Application;
use \Globalis\PuppetSkilled\Library\FormValidation;
use Carbon\Carbon;

class Authentication extends \Globalis\PuppetSkilled\Service\Base
{
    protected $userSessionKey = 'authentication.user';

    protected $permissionsSessionKey = 'authentication.capabilities';

    protected $resources = [];

    protected $user;

    protected $tokenTable = 'reset_tokens';

    public function setTokenTable(string $table)
    {
        $this->tokenTable = $table;
    }

    public function getTokenTable()
    {
        return $this->tokenTable;
    }

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

    public function userCan($permission, $resourceType = null, $resourceValue = null)
    {
        if ($currentPermissions = $this->permissions()) {
            return $currentPermissions->isAllowed($permission, $resourceType, $resourceValue);
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

    /***************************************************************************
     * RESET TOKEN
     **************************************************************************/

    /**
     * Retrieve a reset token
     *
     * @param string $token
     * @return null|stdClass Token
     */
    public function retrieveResetToken($token)
    {
        return $this->queryBuilder->from($this->getTokenTable())
            ->where('token', $token)
            ->first();
    }

    /**
     * Delete a reset token
     *
     * @param string $token
     * @return boolean
     */
    public function deleteToken($token)
    {
        return $this->queryBuilder->from($this->getTokenTable())
            ->where('token', $token)
            ->delete();
    }

    /**
     * Create a reset token
     *
     * @param  \App\Model\User $user
     * @return string
     */
    public function registerToken(\Globalis\PuppetSkilled\Database\Magic\Model $user)
    {
        $this->queryBuilder->from($this->getTokenTable())
            ->insert([
                'user_id' => $user->getKey(),
                'token' => ($token = $this->generateResetToken()),
                'created_at' => new Carbon(),
            ]);
        return $token;
    }

    public function isLoggedIn()
    {
        return ($this->user() ?  true : false);
    }

    public function getResources()
    {
        return $this->resources;
    }

    protected function generateResetToken()
    {
        $string = '';
        while (($len = strlen($string)) < 40) {
            $size = 40 - $len;
            $bytes = $this->security->get_random_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }
        return $string;
    }
}
