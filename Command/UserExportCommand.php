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

class UserExportCommand extends ContainerAwareCommand
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
            ->setName('tutei:user-export')
            ->addArgument('id', InputArgument::REQUIRED, 'the user id to be purged.')
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
        $this->locationService = $this->repository->getLocationService();
        $this->urlAliasService = $this->repository->getURLAliasService();
        $this->contentTypeService = $this->repository->getContentTypeService();
    }
    protected function contentToArray( $content )
    {
        $item = [];
        $item['type'] = $this->contentTypeService->loadContentType( $content->contentInfo->contentTypeId )->identifier;
        foreach( $content->fields as $index => $field )
        {
            $item[$index] = $content->getFieldValue( $index );
        }
        $dt = $content->contentInfo->publishedDate;
        $dt->setTimeZone(new \DateTimeZone('America/New_York'));
        $item['published'] = $dt->format('d/m/Y \a\t H:i \E\S\T');
        $item['paths'] = [];
        $locations = $this->locationService->loadLocations( $content->contentInfo );
        foreach ( $locations as $location )
        {
            $urlAlias = $this->urlAliasService->reverseLookup( $location  );
            $item['paths'][] = $urlAlias->path;
        }
        return $item;
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
            $items = [];
            $userService = $this->repository->getUserService();
            $user = $contentService->loadContent( $userId );
            $items['user_info'] = $this->contentToArray( $user );

            $contentInfo = $contentService->loadContentInfo( $userId );
            $createdCriterion = new Criterion\UserMetadata( Criterion\UserMetadata::OWNER, Criterion\Operator::EQ, $userId );
            $query = new Query();
            $query->filter = new Criterion\LogicalAnd(
                array(
                    $createdCriterion
                )
            );

            $result = $searchService->findContent( $query );
            foreach( $result->searchHits as $content)
            {
                $items[] = $this->contentToArray( $content->valueObject );
            }
            
            $output->writeln( json_encode($items, JSON_PRETTY_PRINT) );
        } catch (Exceptions\NotFoundException $e) {
            $output->writeln('Can\'t find content id: ' . $userId);
        } catch (Exceptions\UnauthorizedException $e) {
            $output->writeln( 'Unauthorized access to content id: ' . $userId );
        }
    }
}