<?php

namespace BNS\App\NotificationBundle\Notification\BlogBundle;

use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 24/10/2012 14:17:36
 */
class BlogArticlePendingCorrectionNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'BLOG_ARTICLE_PENDING_CORRECTION';

	/**
	 * @param type $articleId
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(ContainerInterface $container, $articleId, $groupId = null)
	{
		parent::__construct();
		$this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
			'articleId' => $articleId,
            'groupId' => $groupId
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
		$article = BlogArticleQuery::create()
			->joinWith('User')
			->add(BlogArticlePeer::ID, $objects['articleId'])
		->findOne();

        $finalObjects = array();

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }

		if (null == $article) {
			throw new NotFoundHttpException('The article with ID ' . $objects['articleId'] . ' is not found !');
		}
		$finalObjects['%articleTitle%']		= $article->getTitle();
		$finalObjects['%articleUrl%']		= $notification->getBaseUrl() . self::$container->get('cli.router')->generate('blog_manager_edit_article', array(
			'articleSlug' => $article->getSlug()
		));

		/*
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */

		return parent::getTranslation($notification, $finalObjects);
	}
}
