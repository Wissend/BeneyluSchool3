<?php

namespace BNS\App\NotificationBundle\Notification\HelloWorldBundle;

use BNS\App\CoreBundle\Model\Notification;
use BNS\App\NotificationBundle\Notification\INotification;
use BNS\App\CoreBundle\Model\User;

/**
 * Notification generation date : 28/05/2012 11:38:16 
 */
class HelloWorldExampleWithAttributeAndDisabledEngineNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'HELLO_WORLD_EXAMPLE_WITH_ATTRIBUTE_AND_DISABLED_ENGINE';
	
	/**
	 * @param User $targetUser L'utilisateur qui va recevoir la notification
	 * @param type $testAttribute 
	 * @param type $exampleAttribute 
	 * @param type $pixelCookersName 
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(User $targetUser, $testAttribute, $exampleAttribute, $pixelCookersName, $groupId = null)
	{
		parent::__construct();
		$this->init($targetUser, $groupId, self::NOTIFICATION_TYPE, array(
			'testAttribute' => $testAttribute,
			'exampleAttribute' => $exampleAttribute,
			'pixelCookersName' => $pixelCookersName,
		));
	}
	
	/**
	 * @param Notification $notification
	 * @param array $objects Les paramètres de la notifications
	 * 
	 * @return array Les traductions de la notification 
	 */
	public static function translate(Notification $notification, $objects)
	{
		$finalObjects = array();
		
		// Faites les modifications nécessaires à la restitution des paramètres ci-dessous
		$finalObjects['%testAttribute%'] = $objects['testAttribute'];
		$finalObjects['%exampleAttribute%'] = $objects['exampleAttribute'];
		$finalObjects['%pixelCookersName%'] = $objects['pixelCookersName'];
				
		/* 
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */
		
		return parent::getTranslation($notification, $finalObjects);
	}
}