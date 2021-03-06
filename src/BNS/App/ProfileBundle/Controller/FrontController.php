<?php

namespace BNS\App\ProfileBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\ProfileQuery;
use BNS\App\CoreBundle\Model\ProfileCommentQuery;
use BNS\App\CoreBundle\Model\ProfileCommentPeer;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\ProfileFeedPeer;
use BNS\App\CoreBundle\Model\ProfileFeedQuery;
use BNS\App\CoreBundle\Model\ProfileFeed;

/**
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 * @author Eric Chau		<eric.chau@pixel-cookers.com>
 */
class FrontController extends Controller
{
	/**
	 * @Route("/profil", name="BNSAppProfileBundle_front")
	 * @RightsSomeWhere("PROFILE_ACCESS")
	 */
    public function indexAction($userSlug = null)
    {
		if (null == $userSlug && $this->get('bns.user_manager')->setUser($this->getUser())->getMainRole() == 'parent') {
			// TODO what if parent more than one child ?
			$children = $this->get('bns.user_manager')->getUserChildren();
			return $this->redirect($this->generateUrl('BNSAppProfileBundle_view_profile', array(
				'userSlug' => $children[0]->getSlug()
			)));
		}

		$user = null;
		if (null == $userSlug) {
			$user = $this->getUser();
		}
		else {
			$user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
		}

		$this->canViewProfile($user);

        if (null === $userSlug) {
            // add stat only on index page
            $this->get('stat.profile')->visit();
        }

		// Affiche le profil de l'utilisateur connecté
		$user->setProfile($this->getProfile($user));

		return $this->render('BNSAppProfileBundle:Front:front_profile_index.html.twig', array(
			'classrooms'		=> $this->get('bns.right_manager')->getClassroomsUserBelong($user),
            'user'				=> $user,
			'nb_feeds_per_load'	=> ProfileFeed::PROFILE_FEED_LIMIT,
        ));
    }

	/**
	 * @Route("/profil/voir/{userSlug}", name="BNSAppProfileBundle_view_profile")
	 * @RightsSomeWhere("PROFILE_ACCESS")
	 */
	public function showForeignUserProfile($userSlug)
	{
		return $this->indexAction($userSlug);
	}

	/**
	 * @param type $owner
	 * @return
	 *
	 * @throws NotFoundHttpException
	 */
	private function getProfile($owner)
	{
		$profiles = ProfileQuery::create()
			->joinWith('User')
			->joinWith('ProfilePreference', \Criteria::LEFT_JOIN)
			->add(UserPeer::ID, $owner->getId())
		->find();

		// L'utilisateur existe ?
		if (!isset($profiles[0])) {
			throw new NotFoundHttpException('Profile with id : ' . $owner->getId() . ' is not found !');
		}

		$profile = $profiles[0];
		$query = ProfileFeedQuery::create()
			->joinWith('ProfileFeedStatus', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedResource', \Criteria::LEFT_JOIN)
			->addDescendingOrderByColumn(ProfileFeedPeer::DATE)
			->add(ProfileFeedPeer::PROFILE_ID, $profile->getId())
			->limit(ProfileFeed::PROFILE_FEED_LIMIT)
		;

		if ($this->getUser()->getId() == $owner->getId()) {
			$query->add(ProfileFeedPeer::STATUS, 2, \Criteria::NOT_EQUAL);
		}
		else {
			$query->add(ProfileFeedPeer::STATUS, 1);
		}

		$feeds = $query->find();
		$commentQuery = ProfileCommentQuery::create('c')
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->orderBy('c.Date', \Criteria::DESC)
		;

		if ($this->get('bns.right_manager')->hasRight('PROFILE_ADMINISTRATION')) {
			$commentQuery->where('c.Status != ?', 'REFUSED');
		}
		else {
			$commentQuery->where('c.Status = ?', 'VALIDATED')
				  ->orWhere('c.AuthorId = ?', $this->getUser()->getId())
				  ->where('c.Status != ?', 'REFUSED')
			;
		}

		$feeds->populateRelation('ProfileComment', $commentQuery);
		$profile->replaceProfileFeeds($feeds);

		return $profile;
	}

	/**
	 * @Route("/profil/charger-statut", name="status_load_more", options={"expose"=true})
	 * @RightsSomeWhere("PROFILE_ACCESS")
	 */
	public function loadMoreStatusAction()
	{
		$request = $this->getRequest();
		if (!$request->isXmlHttpRequest()) {
			throw new \Exception('You can only access to this action by AJAX');
		}

		if ('POST' != $request->getMethod() || null == $request->get('profile_id') || null == $request->get('nb_load')) {
			throw new \HttpException('500','Request\'s method should be POST; Two parameters (profile_id and nb_load) must be given!');
		}

		$profiles = ProfileQuery::create('p')
			->joinWith('User')
			->joinWith('ProfilePreference', \Criteria::LEFT_JOIN)
			->where('p.Id = ?', $request->get('profile_id'))
		->find();

		// L'utilisateur existe ?
		if (!isset($profiles[0])) {
			throw new NotFoundHttpException('Profile with id : ' . $request->get('profile_id') . ' is not found !');
		}

		$profile = $profiles[0];

		$query = ProfileFeedQuery::create()
			->joinWith('ProfileFeedStatus', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedResource', \Criteria::LEFT_JOIN)
			->addDescendingOrderByColumn(ProfileFeedPeer::DATE)
			->add(ProfileFeedPeer::PROFILE_ID, $request->get('profile_id'))
			->add(ProfileFeedPeer::STATUS, 1)
			->offset(ProfileFeed::PROFILE_FEED_LIMIT * $request->get('nb_load'))
			->limit(ProfileFeed::PROFILE_FEED_LIMIT)
		;

		if ($this->getUser()->getId() == $profile->getUser()->getId()) {
			$query->add(ProfileFeedPeer::STATUS, 2, \Criteria::NOT_EQUAL);
		}
		else {
			$query->add(ProfileFeedPeer::STATUS, 1);
		}

		$feeds = $query->find();
		$commentQuery = ProfileCommentQuery::create('c')
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->orderBy('c.Date', \Criteria::DESC)
		;

		if ($this->get('bns.right_manager')->hasRight('PROFILE_ADMINISTRATION')) {
			$commentQuery->where('c.Status != ?', 'REFUSED');
		}
		else {
			$commentQuery->where('c.Status = ?', 'VALIDATED')
				  ->orWhere('c.AuthorId = ?', $this->getUser()->getId())
				  ->where('c.Status != ?', 'REFUSED')
			;
		}

		$feeds->populateRelation('ProfileComment', $commentQuery);

		return $this->render('BNSAppProfileBundle:Status:front_status_list.html.twig', array(
			'feeds'	=> $feeds,
		));
	}

	/**
	 * @Route("/profil/statut/{feedId}/voir", name="profile_feed_visualisation")
	 * @RightsSomeWhere("PROFILE_ACCESS")
	 */
	public function visualisationAction($feedId)
	{
		$feeds = ProfileFeedQuery::create('f')
			->joinWith('Profile')
			->joinWith('Profile.User')
			->joinWith('Profile.ProfilePreference', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', \Criteria::LEFT_JOIN)
			->where('f.Id = ?', $feedId)
			->where('f.Status <> ?', 'REFUSED')
		->find();

		$feeds->populateRelation('ProfileComment', ProfileCommentQuery::create()
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->addDescendingOrderByColumn(ProfileCommentPeer::DATE)
			->add(ProfileCommentPeer::STATUS, 2, \Criteria::NOT_EQUAL)
		);

		if (!isset($feeds[0])) {
			throw new NotFoundHttpException('The feed with id : ' . $feedId . ' is NOT found !');
		}

		$user = $feeds[0]->getProfile()->getUser();

		$this->canViewProfile($user);

		return $this->render('BNSAppProfileBundle:Front:front_profile_index.html.twig', array(
			'feeds'				=> $feeds,
			'user'				=> $user,
			'classrooms'		=> $this->get('bns.right_manager')->getClassroomsUserBelong($user)
		));
	}

	/**
	 * @param User $user
	 *
	 * @return boolean
	 */
	protected function canViewProfile($user)
	{
		if ($this->getUser()->getId() == $user->getId()) {
			return true;
		}
		else {
			$gm = $this->get('bns.group_manager');
			$authorisedGroups = $this->get('bns.rightManager')->getGroupsWherePermission('PROFILE_ACCESS');

			foreach ($authorisedGroups as $group) {
				$gm->setGroup($group);
				if (in_array($user->getId(),$gm->getUsersIds())) {
					return true;
				}
			}
		}

		$this->get('bns.right_manager')->forbidIf(true);
	}
}
