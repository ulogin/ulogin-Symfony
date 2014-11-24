<?php

namespace Ulogin\AuthBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UloginUser
 */
class UloginUser
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var integer
     */
    private $userId;

    /**
     * @var string
     */
    private $identity;

    /**
     * @var string
     */
    private $network;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set identity
     *
     * @param string $identity
     * @return UloginUser
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;

        return $this;
    }

    /**
     * Get identity
     *
     * @return string 
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * Set network
     *
     * @param string $network
     * @return UloginUser
     */
    public function setNetwork($network)
    {
        $this->network = $network;

        return $this;
    }

    /**
     * Get network
     *
     * @return string 
     */
    public function getNetwork()
    {
        return $this->network;
    }

    /**
     * Set userId
     *
     * @param integer $userId
     * @return UloginUser
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Get userId
     *
     * @return integer 
     */
    public function getUserId()
    {
        return $this->userId;
    }
}
