<?php
namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BackPaasSubscriptionController extends Controller
{
    /**
     * @Route("/gestion/subscriptions/{page}", requirements={"page"="\d*"})
     * @Template("BNSAppClassroomBundle:BackPaasSubscription:subscription.html.twig")
     * @Rights("SCHOOL_ACCESS_BACK")
     * @RightsSomeWhere("SPOT_ACCESS")
     */
    public function subscriptionAction(Request $request, $page = 1)
    {
        $filters = array_intersect(array('current', 'ending', 'ended'), explode(',', $request->get('filters')));
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        $school = $this->get('bns.right_manager')->getCurrentGroup();
        $subscriptions = $this->get('bns.paas_manager')->getFormattedSubscriptions($school);

        if (count($filters) > 0) {
            $subscriptions = array_filter($subscriptions, function($val) use ($filters) {
                return in_array($val['status'], $filters);
            });
        }

        $pager = new Pagerfanta(new ArrayAdapter($subscriptions));
        $pager->setNormalizeOutOfRangePages(true);
        $pager->setMaxPerPage(5);
        $pager->setCurrentPage($page);

        return array(
            'subscriptions' => $pager,
            'page'          => $page,
            'filters'       => $filters,
            'type'          => 'school',
            'hasGroupBoard' => $hasGroupBoard
        );
    }
}
