<?php

namespace Hofff\Contao\Solr\Source;

use Hofff\Contao\Solr\Index\RequestHandler;
use Hofff\Contao\Solr\Index\Builder\DataImportHandlerQueryBuilder;
use Hofff\Contao\Solr\Index\QueryExecutor;

class ContaoPageSource extends AbstractDCAConfiguredSource {

	public function __construct(array $data) {
		parent::__construct($data);
	}

	public function getDocumentTypes() {
		return array('page', 'image');
	}

	public function getFields() {
		return array();
	}

	public function index(RequestHandler $handler) {
		$executor = new QueryExecutor;

		$builder = new DataImportHandlerQueryBuilder;
		$builder->setCommand(DataImportHandlerQueryBuilder::COMMAND_ABORT);
		$executor->execute($handler, $builder->createQuery());

		$builder = new DataImportHandlerQueryBuilder;
		$builder->setCommand(DataImportHandlerQueryBuilder::COMMAND_RELOAD_CONFIG);
		$executor->execute($handler, $builder->createQuery());

		$this->generatePagesFile();

		$builder = new DataImportHandlerQueryBuilder;
		$builder->setCommand(DataImportHandlerQueryBuilder::COMMAND_FULL_IMPORT);

		$builder->setCommit(true);
		$builder->setOptimize(true);
		$query = $builder->createQuery();
		$query->setParam('source', $this->getName());
		$query->setParam('pages', \Environment::get('base') . $this->getPagesFilePath());
		$executor->execute($handler, $query);
	}

	public function unindex(RequestHandler $handler) {
		$executor = new QueryExecutor;

		$builder = new DataImportHandlerQueryBuilder;
		$builder->setCommand(DataImportHandlerQueryBuilder::COMMAND_ABORT);
		$executor->execute($handler, $builder->createQuery());

		$builder = new DataImportHandlerQueryBuilder;
		$builder->setCommand(DataImportHandlerQueryBuilder::COMMAND_FULL_IMPORT);
		$builder->setClean(true);
		$builder->setCommit(true);
		$query = $builder->createQuery();
		$query->setParam('source', $this->getName());
		$query->setParam('unindex', 1);
		$executor->execute($handler, $query);
	}

	public function status(RequestHandler $handler) {
		$executor = new QueryExecutor;

		$builder = new DataImportHandlerQueryBuilder;
		$builder->setCommand(DataImportHandlerQueryBuilder::COMMAND_STATUS);
		return $executor->execute($handler, $builder->createQuery());
	}

	public function getRoots() {
		return array_filter(array_map('intval', deserialize($this['page_roots'], true)));
	}

	public function isIndexImages() {
		return (bool) $this['index_images'];
	}

	public function getPagesFilePath() {
		return sprintf(
			'system/cache/hofff-solr/%d-%s.txt',
			$this['id'],
			standardize($this->getName())
		);
	}

	protected function generatePagesFile() {
		$roots = $this->getRoots() ?: array(0);
		$pages = \Database::getInstance()->getChildRecords($roots, 'tl_page');
		$content = implode(',', $pages);

		$file = TL_ROOT . '/' . $this->getPagesFilePath();
		$dir = dirname($file);
		is_dir($dir) || mkdir($dir, 0777, true);

		if(false === file_put_contents($file, $content)) {
			throw new \Exception;
		}
	}

}
