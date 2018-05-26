<?php


namespace Tutei\GDPRBundle\Command;

use eZ\Publish\API\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query;
use \eZ\Publish\API\Repository\Exceptions;

class HideUserContentCommand extends ContainerAwareCommand
{
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    private $repository;


    public function __construct(Repository $repository)
    {
        parent::__construct(null);
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('tutei:user-hide')
            ->addArgument('id', InputArgument::REQUIRED, 'the user id to be disabled hidding all his contents.')
            ->addOption(
                'script_user',
                'u',
                InputOption::VALUE_OPTIONAL,
                'eZ Platform username (with Role containing at least Content policies: read, versionread, edit, remove, versionremove)',
                'admin'
            )
            ->setDescription('Deletes a certain user and its content.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->repository->getPermissionResolver()->setCurrentUserReference(
            $this->repository->getUserService()->loadUserByLogin($input->getOption('script_user'))
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $searchService = $this->repository->getSearchService();
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $userService = $this->repository->getUserService();

        $userId = (int)$input->getArgument('id');
        $createdCriterion = new Criterion\UserMetadata( Criterion\UserMetadata::OWNER, Criterion\Operator::EQ, $userId );
        try {
            $contentInfo = $contentService->loadContentInfo( $userId );
            $query = new Query();
            $query->filter = new Criterion\LogicalAnd(
                array(
                    $createdCriterion
                )
            );

            $result = $searchService->findContent( $query );
            foreach( $result->searchHits as $content)
            {
                $locations = $locationService->loadLocations($content->valueObject->contentInfo);
                foreach( $locations as $location )
                {
                    $locationService->hideLocation($location);
                }
            }

            $ezUser =  $userService->loadUser($userId);
            $userUpdateStruct = $userService->newUserUpdateStruct();

            $userUpdateStruct->enabled = false;

            $updatedUser = $userService->updateUser($ezUser, $userUpdateStruct);

        } catch (Exceptions\NotFoundException $e) {
            $output->writeln('Can\'t find content id: ' . $userId);
        } catch (Exceptions\UnauthorizedException $e) {
            $output->writeln( 'Unauthorized access to content id: ' . $userId );
        }
    }
}