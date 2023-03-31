<?php
namespace OCA\LoginWithPatreon\Db;

use OCP\AppFramework\Db\Entity;

class ConnectedLogin extends Entity
{
    public $id = -1;
    public $uid;
    public $identifier;
}
