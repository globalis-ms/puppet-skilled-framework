<?php
namespace Globalis\PuppetSkilled\Session;

use Globalis\PuppetSkilled\Core\Application;

class SessionDatabaseDriver extends \CI_Session_driver
{
    /**
     * DB object
     *
     * @var    object
     */
    protected $connection;

    /**
     * Row exists flag
     *
     * @var    bool
     */
    protected $row_exists = false;

    /**
     * Lock "driver" flag
     *
     * @var    string
     */
    protected $platform;

    /**
     * Class constructor
     *
     * @param   array    $params    Configuration parameters
     * @return  void
     */
    public function __construct($params)
    {
        parent::__construct($params);
        // Note: BC work-around for the old 'sess_table_name' setting, should be removed in the future.
        if (!isset($this->_config['save_path']) && ($this->_config['save_path'] = config_item('sess_table_name'))) {
            log_message('debug', 'Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".');
        }
    }

    /**
     * Open
     *
     * Initializes the database connection
     *
     * @param    string    $savePath    Table name
     * @param    string    $name        Session cookie name, unused
     * @return    bool
     */
    public function open($savePath, $name)
    {
        return $this->_success;
    }

    /**
     * Read
     *
     * Reads session data and acquires a lock
     *
     * @param    string    $sessionId    Session ID
     * @return    string    Serialized session data
     */
    public function read($sessionId)
    {
        if ($this->get_lock($sessionId) !== false) {
            // Needed by write() to detect session_regenerate_id() calls
            $this->_session_id = $sessionId;

            $query = $this->newQuery()
                ->select('data')
                ->where('id', $sessionId);

            if ($this->_config['match_ip']) {
                $query->where('ip_address', $_SERVER['REMOTE_ADDR']);
            }

            if (($result = $query->first()) === null) {
                // PHP7 will reuse the same SessionHandler object after
                // ID regeneration, so we need to explicitly set this to
                // false instead of relying on the default ...
                $this->_row_exists = false;
                $this->_fingerprint = md5('');
                return '';
            }
            $result = $result->data;
            $this->_fingerprint = md5($result);
            $this->_row_exists = true;
            return $result;
        }
        $this->_fingerprint = md5('');
        return '';
    }

    /**
     * Write
     *
     * Writes (create / update) session data
     *
     * @param    string    $sessionId    Session ID
     * @param    string    $session_data    Serialized session data
     * @return    bool
     */
    public function write($sessionId, $sessionData)
    {
        if ($this->_lock === false) {
            return $this->_fail();
        } elseif ($sessionId !== $this->_session_id) {
            // Was the ID regenerated?
            if (!$this->_release_lock() || !$this->get_lock($sessionId)) {
                return $this->_fail();
            }

            $this->_row_exists = false;
            $this->_session_id = $sessionId;
        }

        if ($this->_row_exists === false) {
            $insertData = array(
                'id' => $sessionId,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => time(),
                'data' => $sessionData
            );

            if ($this->newQuery()->insert($insertData)) {
                $this->_fingerprint = md5($sessionData);
                $this->_row_exists = true;
                return $this->_success;
            }

            return $this->_fail();
        }

        $query = $this->newQuery()
            ->where('id', $sessionId);
        if ($this->_config['match_ip']) {
            $query->where('ip_address', $_SERVER['REMOTE_ADDR']);
        }

        $update_data = array('timestamp' => time());
        if ($this->_fingerprint !== md5($sessionData)) {
            $update_data['data'] = $sessionData;
        }

        if ($query->update($update_data)) {
            $this->_fingerprint = md5($sessionData);
            return $this->_success;
        }

        return $this->_fail();
    }

    /**
     * Close
     *
     * Releases locks
     *
     * @return    bool
     */
    public function close()
    {
        return ($this->_lock && !$this->_release_lock())
            ? $this->_fail()
            : $this->_success;
    }

    /**
     * Destroy
     *
     * Destroys the current session.
     *
     * @param    string    $sessionId    Session ID
     * @return    bool
     */
    public function destroy($sessionId)
    {
        if ($this->_lock) {
            $query = $this->newQuery()
                ->where('id', $sessionId);
            if ($this->_config['match_ip']) {
                $query->where('ip_address', $_SERVER['REMOTE_ADDR']);
            }

            if (!$query->delete()) {
                return $this->_fail();
            }
        }

        if ($this->close() === $this->_success) {
            $this->_cookie_destroy();
            return $this->_success;
        }

        return $this->_fail();
    }

    /**
     * Garbage Collector
     *
     * Deletes expired sessions
     *
     * @param    int     $maxlifetime    Maximum lifetime of sessions
     * @return    bool
     */
    public function gc($maxlifetime)
    {
        return ($this->newQuery()->where('timestamp', '<', time() - $maxlifetime)->delete())
            ? $this->_success
            : $this->_fail();
    }

    /**
     * Get lock
     *
     * Acquires a lock, depending on the underlying platform.
     *
     * @param    string    $sessionId    Session ID
     * @return    bool
     */
    protected function get_lock($sessionId)
    {
        $arg = md5($sessionId.($this->_config['match_ip'] ? '_'.$_SERVER['REMOTE_ADDR'] : ''));
        if ($this->getConnection()->query("SELECT GET_LOCK('".$arg."', 300) AS ci_session_lock")->row()->ci_session_lock) {
            $this->_lock = $arg;
            return true;
        }

        return false;
    }

    /**
     * Release lock
     *
     * Releases a previously acquired lock
     *
     * @return    bool
     */
    protected function _release_lock()
    {
        if (!$this->_lock) {
            return true;
        }
        if ($this->getConnection()->query("SELECT RELEASE_LOCK('".$this->_lock."') AS ci_session_lock")->row()->ci_session_lock) {
            $this->_lock = false;
            return true;
        }
        return false;
    }

    protected function newQuery()
    {
        return Application::getInstance()->queryBuilder->from($this->_config['save_path']);
    }

    protected function getConnection()
    {
        return Application::getInstance()->db;
    }
}
