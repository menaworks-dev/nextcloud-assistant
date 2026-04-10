<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class AssistantController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ITaskProcessingManager $taskProcessingManager,
		private IInitialState $initialStateService,
		private IConfig $config,
		private IAppConfig $appConfig,
		private SessionMapper $sessionMapper,
		private MessageMapper $messageMapper,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $taskId
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAssistantTaskResultPage(int $taskId): TemplateResponse {
		if ($this->userId !== null) {
			try {
				$task = $this->taskProcessingManager->getTask($taskId);
				if ($task->getUserId() === $this->userId) {
					$this->initialStateService->provideInitialState('task', $task->jsonSerialize());
					return new TemplateResponse(Application::APP_ID, 'assistantPage');
				}
			} catch (Exception|\Throwable $e) {
			}
		}
		return new TemplateResponse('', '403', [], TemplateResponse::RENDER_AS_ERROR, Http::STATUS_FORBIDDEN);
	}

	/**
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAssistantStandalonePage(): TemplateResponse {
		if ($this->userId !== null) {
			$task = new Task(
				$this->config->getUserValue($this->userId, Application::APP_ID, 'last_task_type', 'chatty-llm'),
				['something' => ''],
				Application::APP_ID,
				$this->userId,
				''
			);
			$serializedTask = $task->jsonSerialize();
			// otherwise the task id is 0 and the default input shape values are not set
			$serializedTask['id'] = null;
			$this->initialStateService->provideInitialState('task', $serializedTask);
			return new TemplateResponse(Application::APP_ID, 'assistantPage');
		}
		return new TemplateResponse('', '403', [], TemplateResponse::RENDER_AS_ERROR, Http::STATUS_FORBIDDEN);
	}

	/**
	 * Prepare messages for streaming — returns payload for direct proxy call
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function streamGenerate(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'User not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => 'Session not found'], Http::STATUS_NOT_FOUND);
		}

		$lastNMessages = intval($this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10'));
		$allMessages = $this->messageMapper->getMessages($sessionId, 0, $lastNMessages);
		$systemPrompt = '';
		if (count($allMessages) > 0 && $allMessages[0]->getRole() === 'system') {
			$systemPrompt = $allMessages[0]->getContent();
			array_shift($allMessages);
		}

		$messages = [];
		if ($systemPrompt !== '') {
			$messages[] = ['role' => 'system', 'content' => $systemPrompt];
		}
		foreach ($allMessages as $msg) {
			$role = $msg->getRole() === 'human' ? 'user' : 'assistant';
			$messages[] = ['role' => $role, 'content' => $msg->getContent()];
		}

		$proxyKey = $this->appConfig->getValueString('integration_openai', 'api_key', '', true);

		return new JSONResponse([
			'messages' => $messages,
			'apiKey' => $proxyKey,
			'user' => $this->userId,
		]);
	}

}
