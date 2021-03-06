<?php
namespace BNS\App\StatisticsBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MainBundle\Statistic\ConnectionStatistic;
use BNS\App\MainBundle\Statistic\VisitStatistic;
use BNS\App\StatisticsBundle\Exception\InvalidArgumentException;
use BNS\App\StatisticsBundle\Exception\InvalidGraphException;
use BNS\App\StatisticsBundle\Exception\InvalidStatisticException;
use BNS\App\StatisticsBundle\Model\BlogQuery;
use BNS\App\StatisticsBundle\Model\MarkerQuery;
use BNS\App\StatisticsBundle\Statistics\Graph;
use BNS\App\StatisticsBundle\Statistics\Indicator;
use BNS\App\StatisticsBundle\Statistics\StatisticInterface;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class StatisticManager
{
    /** @var array<StatisticInterface>  */
    protected $statistics = array();

    protected $groupManager;

    protected $userManager;

    protected $groups = array();

    protected $translator;

    public function __construct(BNSGroupManager $groupManager, BNSUserManager $userManager, TranslatorInterface $translator)
    {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->translator = $translator;

        // TODO : inject tagged services
        $this->statistics = array(
//            new VisitStatistic(),
//            new ConnectionStatistic(),
        );
    }

    public function getStatistics()
    {
        return $this->statistics;
    }

    public function addStatistic(StatisticInterface $statistic)
    {
        $this->statistics[] = $statistic;
    }


    /**
     * get all groups the user can view (STATISTICS_ACCESS)
     * And subgroups SCHOOL when STATISTICS_SCHOOL or subgroups CLASSROOM when STATISTICS_CLASSROOM
     *
     * @return \PropelObjectCollection|mixed|array
     */
    public function getGroups(User $user)
    {
        $userId = $user->getId();
        if (!isset($this->groups[$userId])) {
            $groupIds = $this->userManager->setUser($user)->getGroupIdsWherePermission('STATISTICS_ACCESS');

            $schoolIds = array();
            $classroomIds = array();
            foreach ($groupIds as $groupId) {
                if ($this->userManager->hasRight('STATISTICS_SCHOOL', $groupId)) {
                    $schoolGroupIds = $this->groupManager->getAllSubgroups($groupId, 'SCHOOL', false);
                    $schoolIds = array_merge($schoolIds, $schoolGroupIds);
                }
//                if ($this->userManager->hasRight('STATISTICS_CLASSROOM', $groupId)) {
//                    $classroomGroupIds = $this->groupManager->getAllSubgroups($groupId, 'CLASSROOM', false);
//                    $classroomIds = array_merge($classroomIds, $classroomGroupIds);
//                }
            }

            $groupIds = array_merge($groupIds, $schoolIds, $classroomIds);

            $this->groups[$userId] = GroupQuery::create()
                ->filterByEnabledOnly($this->groupManager->getCheckGroupValidated(), $this->groupManager->getCheckGroupEnabled())
                ->filterById($groupIds)
                ->orderByGroupTypeId(\Criteria::ASC)
                ->orderByLabel(\Criteria::ASC)
                ->find()
            ;
        }

        return $this->groups[$userId];
    }

    public function getGraphData($statisticName, $graphName, $filters)
    {
        if (! ($statistic = $this->getStatistic($statisticName))) {
            throw new InvalidStatisticException(sprintf('Stat %s does not exist', $statisticName));
        }

        if (!($graph = $statistic->getGraph($graphName))) {
            throw new InvalidGraphException(sprintf('Stat %s does not exist', $graphName));
        }

        if (false !== ($data = $statistic->getGraphData($graph, $filters))) {
            return $data;
        }

        $indicators = $graph->getIndicators();

        $groups = $filters['groups'];
        $childGroups = array();
        if ($graph->isUnDuplicateGroup()) {
            $unDuplicateGroups = $this->unDublipcateGroups($filters['groups']);
            $filters['groups'] = $unDuplicateGroups['groups'];
            $childGroups = $unDuplicateGroups['childGroups'];
        }

        $data = array();
        /** @var Indicator $indicator */
        foreach ($indicators as $indicator) {
            $code = $indicator->getCode();
            $query = $this->getStatQuery($code, $filters);
            if ($graph->getRoleMode() === Graph::ROLE_MODE_DISTINCT) {
                $query->groupByRoleId();

                if (count($graph->getRoles()) > 0) {
                    $query->filterByRoleId($this->getRoleIds($graph->getRoles()));
                }

                $datas = $query->find();
                foreach ($graph->getRoles() as $role) {
                    $data[] = array(
                        /** @Ignore */
                        'name' => $this->translator->trans('statistic.indicator.' . $indicator->getName() . '.by_role.'.$role),
                        'data'  => $statistic->transformDataToGraph($datas, $this->getRoleIds($role)),
                    );
                }


            } else {
                $data[] = array(
                    /** @Ignore */
                    'name' => $this->translator->trans('statistic.indicator.' . $indicator->getName()),
                    'data'  => $statistic->transformDataToGraph($query->find())
                );
            }

        }

        return array(
            'config' => array(
                'options' => $graph->getOptions(),
                'title'   => array('text' => ''),
                'series'  => $data,
                'yAxis'   => $graph->getYAxis($filters),
                'xAxis'   => $graph->getXAxis($filters),
            ),
            'groups'      => $groups,
            'childGroups' => $childGroups,
            /** @Ignore */
            'title'   => $this->translator->trans('statistic.graph.title.' . $graph->getTitle()),
        );
    }

    public function getTableData($statisticName, $filters)
    {
        if (! ($statistic = $this->getStatistic($statisticName))) {
            throw new InvalidStatisticException(sprintf('Stat %s does not exist', $statisticName));
        }

        // Allow each statistic to override default data builder
        if (false !== $data = $statistic->getTableData($filters)) {
            return $data;
        }

        $indicators = $statistic->getIndicators();

        $data = array();
        /** @var Indicator $indicator */
        foreach ($indicators as $indicator) {
            $code = $indicator->getCode();
            $query = $this->getStatQuery($code, $filters, false);
            $query->filterByRoleId($this->getTableRoleIds());
            $query->groupByRoleId();

            $data[] = array(
                'indicator' => $indicator,
                'data'  => $query
                    ->select(array('MarkerId', 'GroupId', 'RoleId', 'count'))
                    ->find(),

                'enableSelectAll' => true,
                'enableGridMenu' => true,
                'exporterCsvFilename' => 'myFile.csv',
            );
        }

        return $data;
    }

    /**
     * @param \PropelObjectCollection|array $groups
     * @return array
     */
    public function unDublipcateGroups($groups)
    {
        $groupWithSubGroups = array();
        $mainGroups = array();
        $childGroups = array();

        foreach ($groups as $group) {
            $groupWithSubGroups[$group->getId()] = $this->groupManager->getAllSubgroupIds($group->getId());
        }

        foreach ($groups as $group) {
            foreach ($groupWithSubGroups as $groupId => $subGroupIds) {
                if ($group->getId() === $groupId) {
                    continue;
                }
                if (in_array($group->getId(), $subGroupIds)) {
                    // we have a child group
                    $childGroups[$group->getId()] = $group;
                    continue 2;
                }
            }
            $mainGroups[$group->getId()] = $group;
        }

        return array(
            'groups' => $mainGroups,
            'childGroups' => $childGroups,
        );
    }

    protected function getTableRoleIds()
    {
        return $this->getRoleIds(array(
                'TEACHER', 'PARENT', 'PUPIL'
            ))
        ;
    }

    /**
     * @param array|string $roles
     * @return int|array<int>
     */
    protected function getRoleIds($roles)
    {
        $query = GroupTypeQuery::create()
            ->filterByRole()
            ->filterByType($roles)
            ->select('Id')
        ;

        if (!is_array($roles) || count($roles) === 1) {
            return (int)$query->findOne();
        }

        return $query->find()->getArrayCopy();
    }

    /**
     * @param string $statistic
     * @return bool|StatisticInterface
     */
    protected function getStatistic($statistic)
    {
        foreach ($this->statistics as $stat) {
            if ($stat->getName() === $statistic) {
                return $stat;
            }
        }

        return false;
    }


    protected function getStatQuery($marker, $filters, $groupByPeriod = 'DAY')
    {
        $marker = MarkerQuery::create()->filterByUniqueName($marker)->findOne();
        if (!$marker) {
            throw new \Exception(sprintf('invalid marker : %s', $marker));
        }
        $module = $marker->getModuleUniqueName();

        $queryClass = "\\BNS\\App\\StatisticsBundle\\Model\\" . Inflector::classify(strtolower($module)) . "Query";
        if (!class_exists($queryClass)) {
            throw new \Exception(sprintf('invalid query class %s', $queryClass));
        }

        /** @var BlogQuery $query */
        $query = $queryClass::create();

        $query->filterByMarker($marker);
        $query->filterByDate(array(
            'min' => $filters['start'],
            'max' => $filters['end']
        ));
        $groupIds = array();
        foreach ($filters['groups'] as $group) {
            $groupIds[] = $group->getId();
        }


        $query->filterByGroupId($groupIds);
        $query->orderByDate();
        $query->withColumn("SUM(value)", 'count');

        if ($groupByPeriod) {
            switch ($groupByPeriod) {
                case 'MONTH':
                    $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m')";
                    $dateFormatPhp = "Y-m";
                    break;
                case 'HOUR': //pour l'heure il faut d'abords faire un format sur le jour puis l'heure
                    $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m%d %H')";
                    $dateFormatPhp = "Y-m-d H:i:s";
                    break;
                case 'DAY':
                default: // par défaut 'DAY'
                    $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m%d')";
                    $dateFormatPhp = "Y-m-d";
                    break;
            }
            $query->addGroupByColumn($dateFormatPropel);
        }


        return $query;
    }

}
