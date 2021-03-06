<?php

namespace BNS\App\MiniSiteBundle\ApiController;


use BNS\App\CoreBundle\Controller\BaseApiController;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageTextQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteWidgetQuery;
use BNS\App\CoreBundle\Rss\RssManager;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MiniSiteApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class MiniSiteApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Minisite",
     *  resource = true,
     *  description="Get a group's minisite",
     * )
     *
     * @Rest\Get("/groups/{groupId}/minisite")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function getMinisiteAction($groupId)
    {

        /** @var MiniSite $miniSite */
        //Get the minisite by group's id
        $miniSite = MiniSiteQuery::create()
            ->filterByGroupId($groupId)
            ->findOne();

        $group = GroupQuery::create()
            ->findOneById($groupId);

        $rightManager = $this->get('bns.right_manager');
        if (!$miniSite && $rightManager->hasRight('MINISITE_ACCESS', $groupId)) {

            $miniSite = new MiniSite();
            $miniSite->setGroupId($groupId);
            $miniSite->setTitle($this->get('translator')->trans('TITLE_SITE_NAME', array('%name%' => $group->getLabel()), 'MINISITE'));
            $miniSite->save();

            // Create the homepage, many queries are based on the homepage
            $homePage = new MiniSitePage();
            $homePage->setMiniSiteId($miniSite->getId());
            $homePage->setTitle($this->get('translator')->trans('TITLE_WELCOME', array(), 'MINISITE'));
            $homePage->setIsActivated(true);
            $homePage->setType(MiniSitePagePeer::TYPE_TEXT);
            $homePage->setIsHome(true);
            $homePage->save();
        }

        if (!$miniSite) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        //did this user has right to see the minisite?
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('MINISITE_ACCESS', $miniSite->getGroupId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        //return the minisite
        return array (
            'minisite' => $miniSite
        );
    }

    /**
     * @ApiDoc(
     *  section="Minisite",
     *  resource = true,
     *  description="Get a minisite by slug",
     * )
     *
     * @Rest\Get("/minisite/{siteSlug}")
     * @Rest\View(serializerGroups={"Default"})
     */
    public function getMinisiteBySlugAction($siteSlug)
    {
        /** @var MiniSite $miniSite */
        //Get the minisite by group's id
        $miniSite = MiniSiteQuery::create()
            ->filterBySlug($siteSlug, \Criteria::EQUAL)
            ->findOne()
        ;

        //if there is not minisite: not found
        if (!$miniSite) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $miniSiteId = $miniSite->getId();

        //get the page (only fews datas)
        $pagesQuery = MiniSitePageQuery::create()
            ->filterByMiniSiteId($miniSiteId)
        ;

        $user = $this->getUser();

        //if he is not log in
        if (!$user instanceof User) {
            $pagesQuery = $pagesQuery->filterByIsPublic(true);
        } else {
            //did this user has right to see the minisite?
            $rightManager = $this->get('bns.right_manager');
            //if he didn't have the rights in this group, he can see what's public
            if (!$rightManager->hasRight('MINISITE_ACCESS', $miniSite->getGroupId())) {
                $pagesQuery = $pagesQuery->filterByIsPublic(true);
            }
        }

        $pages = $pagesQuery
            ->filterByIsActivated(true)
            ->orderByRank(\Criteria::ASC)
            ->find()
        ;

        //if they doesn't exist: not found
        if (!$pages) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        //get the minisite's widgets
        $widgets = MiniSiteWidgetQuery::create()
            ->filterByMiniSiteId($miniSiteId, \Criteria::EQUAL)
            ->joinWith('MiniSiteWidgetTemplate', \Criteria::LEFT_JOIN)
            ->joinWith('MiniSiteWidgetExtraProperty', \Criteria::LEFT_JOIN)
            ->orderBy('Rank')
            ->find()
        ;

        $miniSite->getBannerUrl();

        if ($this->container->hasParameter('graphic_chart')) {
            $graphicChart = $this->getParameter('graphic_chart');
            $path = 'medias/images/main/graphic_chart/'.$graphicChart['name'].'/logo.png';
            $miniSite->logoUrl = $this->get('templating.helper.assets')->getUrl($path);
        }

        //return the fix content of the minisite
        return array (
            'group' => $miniSite->getGroup(),
            'minisite' => $miniSite,
            'pages' => $pages,
            'widgets' => $widgets
        );
    }

    /**
     * @ApiDoc(
     *  section="Minisite",
     *  resource = false,
     *  description="Get pages of a minisite",
     * )
     *
     * @Rest\Get("/minisite/{siteSlug}/pages/{pageSlug}")
     * @Rest\QueryParam("page", requirements="\d*", default="1")
     * @Rest\QueryParam("limit", requirements="\d*", default="25")
     *
     * @Rest\View(serializerGroups={"Default","user_list","media_basic"})
     */
    public function getPagesAction($siteSlug, $pageSlug, ParamFetcher $paramFetcher)
    {
        $miniSite = MiniSiteQuery::create()
            ->filterBySlug($siteSlug, \Criteria::EQUAL)
            ->findOne()
        ;

        if (!$miniSite) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $pagesQuery = MiniSitePageQuery::create()
            ->filterByMiniSite($miniSite)
            ->filterBySlug($pageSlug, \Criteria::EQUAL)
        ;

        $user = $this->getUser();

        //if he is not log in
        if (!$user instanceof User) {
            $pagesQuery = $pagesQuery->filterByIsPublic(true);
        } else {
            //did this user has right to see the minisite?
            $rightManager = $this->get('bns.right_manager');
            //if he didn't have the rights in this group, he can see what's public
            if (!$rightManager->hasRight('MINISITE_ACCESS', $miniSite->getGroupId())) {
                $pagesQuery = $pagesQuery->filterByIsPublic(true);
            }

            if (!$rightManager->hasRight('MINISITE_ACCESS_BACK', $miniSite->getGroupId())) {
                $pagesQuery = $pagesQuery->filterByIsActivated(true);
            }
        }

        $minisitePage = $pagesQuery
            ->findOne()
        ;

        //if they doesn't exist: not found
        if (!$minisitePage) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $pageId = $minisitePage->getId();

        if ($minisitePage->getType() === 'NEWS'){
            // if it's a news's page
            $content = null;
            $query = MiniSitePageNewsQuery::create()
                ->filterByPageId($pageId)
                ->filterByStatus('PUBLISHED')
                ->orderByCreatedAt('desc')
                ->joinWith('User') // Author
                ->joinWith('User.Profile')
            ;

            $articles = $this->getPaginator($query, new Route('minisite_api_get_pages', array(
                'version' => $this->getVersion(),
                'siteSlug' => $siteSlug,
                'pageSlug' => $pageSlug
            ), true), $paramFetcher);
        } else {
            // if it's a text's page
            $articles = [];
            $content = MiniSitePageTextQuery::create()
                ->filterByPageId($pageId)
                ->findOne()
            ;
        }

        return [
            'page' => $minisitePage,
            'news' => $articles,
            'content' => $content
        ];
    }
    /**
     * @ApiDoc(
     *  section="Minisite",
     *  resource = false,
     *  description="Get widget of a minisite",
     * )
     *
     * @Rest\Get("/minisite/{siteSlug}/widget/{widgetId}")
     *
     * @Rest\View(serializerGroups={"Default","user_list"})
     */
    public function getWidgetAction($siteSlug, $widgetId, ParamFetcher $paramFetcher)
    {
        $miniSite = MiniSiteQuery::create()
            ->filterBySlug($siteSlug, \Criteria::EQUAL)
            ->findOne()
        ;

        if (!$miniSite) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $pagesQuery = MiniSitePageQuery::create()
            ->filterByMiniSite($miniSite)
        ;

        $user = $this->getUser();

        //if he is not log in
        if (!$user instanceof User) {
            $pagesQuery = $pagesQuery->filterByIsPublic(true);
        } else {
            //did this user has right to see the minisite?
            $rightManager = $this->get('bns.right_manager');
            //if he didn't have the rights in this group, he can see what's public
            if (!$rightManager->hasRight('MINISITE_ACCESS', $miniSite->getGroupId())) {
                $pagesQuery = $pagesQuery->filterByIsPublic(true);
            }
        }

        $minisiteCount = $pagesQuery
            ->filterByIsActivated(true)
            ->count()
        ;
        if ($minisiteCount > 0){
            try {
                $rssManager = new RssManager($this->get('snc_redis.default'));
                $widget = MiniSiteWidgetQuery::create()
                    ->filterByMiniSiteId($miniSite->getId(), \Criteria::EQUAL)
                    ->filterById($widgetId)
                    ->joinWith('MiniSiteWidgetExtraProperty', \Criteria::LEFT_JOIN)
                    ->find();
                $rssUrl = $widget[0]->getPropertyValue()[0]->getPropertyValue();
                $rssLimit = $widget[0]->getPropertyValue()[1]->getPropertyValue();
                return $rssManager->getRss($rssUrl, $rssLimit);
            }
            catch (\Exception $e) {
                // Nothing
            }
        } else {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
    }

    /**
     * @ApiDoc(
     *  section="Minisite",
     *  description="Views incrementation",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      403 = "No access",
     *  }
     * )
     * @Rest\Patch("/minisite/{siteSlug}/pages/{pageSlug}")
     * @Rest\View()
     *
     * @param string $siteSlug
     * @param string $pageSlug
     * @param Request $request
     * @return Response
     */
    public function patchAction($siteSlug, $pageSlug, Request $request)
    {
        $miniSite = MiniSiteQuery::create()
            ->filterBySlug($siteSlug, \Criteria::EQUAL)
            ->findOne();

        if(!$miniSite) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $page = MiniSitePageQuery::create()
            ->filterByMiniSite($miniSite)
            ->filterBySlug($pageSlug, \Criteria::EQUAL)
            ->findOne();

        if(!$page) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $views = $page->getViews() + 1;

        $page->setViews($views);
        $page->save();

        return true;
    }
}
