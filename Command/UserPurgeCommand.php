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

class UserPurgeCommand extends ContainerAwareCommand
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
            ->setName('tutei:user-purge')
            ->addArgument('id', InputArgument::REQUIRED, 'the user id to be purged.')
            ->addArgument('contentTypeOrder', InputArgument::REQUIRED, 'list of content type identifiers separated by comma')
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
        $userId = (int)$input->getArgument('id');
        try {
            $contentInfo = $contentService->loadContentInfo( $userId );
            $createdCriterion = new Criterion\UserMetadata( Criterion\UserMetadata::OWNER, Criterion\Operator::EQ, $userId );
            foreach( explode( ',', $input->getArgument('contentTypeOrder') ) as $type )
            {
                $query = new Query();
                $query->filter = new Criterion\LogicalAnd(
                    array(
                        $createdCriterion,
                        new Criterion\ContentTypeIdentifier( $type ),
                    )
                );

                $result = $searchService->findContent( $query );
                foreach( $result->searchHits as $content)
                {
                    $contentService->deleteContent( $content->valueObject->contentInfo );
                }
            }
            $contentService->deleteContent( $contentInfo );
        } catch (Exceptions\NotFoundException $e) {
            $output->writeln('Can\'t find content id: ' . $userId);
        } catch (Exceptions\UnauthorizedException $e) {
            $output->writeln( 'Unauthorized access to content id: ' . $userId );
        }
    }
}