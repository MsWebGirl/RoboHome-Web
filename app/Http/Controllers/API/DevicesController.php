<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\DeviceInformation\IDeviceInformation;
use App\Http\Controllers\Common\Controller;
use App\Http\Globals\DeviceActions;
use App\Http\MQTT\MessagePublisher;
use App\Repositories\IUserRepository;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicesController extends Controller
{
    private $messagePublisher;
    private $deviceInformation;
    private $userRepository;

    public function __construct(MessagePublisher $messagePublisher, IDeviceInformation $deviceInformation, IUserRepository $userRepository)
    {
        $this->middleware('auth:api', ['except' => ['info']]);

        $this->messagePublisher = $messagePublisher;
        $this->deviceInformation = $deviceInformation;
        $this->userRepository = $userRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $devicesForCurrentUser = $currentUser->devices;

        $response = [
            'header' => $this->createHeader($request, 'DiscoverAppliancesResponse', 'Alexa.ConnectedHome.Discovery'),
            'payload' => [
                'discoveredAppliances' => $this->buildAppliancesJson($devicesForCurrentUser)
            ]
        ];

        return response()->json($response);
    }

    public function turnOn(Request $request): JsonResponse
    {
        $response = $this->handleControlRequest($request, DeviceActions::TURN_ON, 'TurnOnConfirmation');

        return $response;
    }

    public function turnOff(Request $request): JsonResponse
    {
        $response = $this->handleControlRequest($request, DeviceActions::TURN_OFF, 'TurnOffConfirmation');

        return $response;
    }

    public function info(Request $request): JsonResponse
    {
        $currentUserId = $request->get('userId');
        $deviceId = $request->get('deviceId');
        $action = $request->get('action');

        $user = $this->userRepository->get($currentUserId);

        $doesUserOwnDevice = $this->doesUserOwnDevice($user, $deviceId);

        if (!$doesUserOwnDevice) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->deviceInformation->info($deviceId, $action);
    }

    private function handleControlRequest(Request $request, string $action, string $responseName): JsonResponse
    {
        $currentUser = $request->user();
        $userId = $currentUser->id;
        $deviceId = $request->input('id');

        $doesUserOwnDevice = $this->doesUserOwnDevice($currentUser, $deviceId);

        if (!$doesUserOwnDevice) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $urlValidAction = strtolower($action);

        $this->messagePublisher->publish($userId, $urlValidAction, $deviceId);

        $response = [
            'header' => $this->createHeader($request, $responseName, 'Alexa.ConnectedHome.Control'),
            'payload' => (object)[]
        ];

        return response()->json($response);
    }

    private function buildAppliancesJson($devicesForCurrentUser): array
    {
        $actions = [DeviceActions::TURN_ON, DeviceActions::TURN_OFF];

        $appliances = [];

        for ($i = 0; $i < count($devicesForCurrentUser); $i++) {
            $appliance = [
                'actions' => $actions,
                'additionalApplianceDetails' => (object)[],
                'applianceId' => $devicesForCurrentUser[$i]->id,
                'friendlyName' => $devicesForCurrentUser[$i]->name,
                'friendlyDescription' => $devicesForCurrentUser[$i]->description,
                'isReachable' => true,
                'manufacturerName' => 'N/A',
                'modelName' => 'N/A',
                'version' => 'N/A'
            ];

            array_push($appliances, $appliance);
        }

        return $appliances;
    }

    private function createHeader(Request $request, string $responseName, string $namespace): array
    {
        $messageId = $request->header('Message-Id');

        $header = [
            'messageId' => $messageId,
            'name' => $responseName,
            'namespace' => $namespace,
            'payloadVersion' => '2'
        ];

        return $header;
    }

    private function doesUserOwnDevice(User $user, int $deviceId): bool
    {
        if ($user === null) {
            return false;
        }

        $doesUserOwnDevice = $user->doesUserOwnDevice($deviceId);

        return $doesUserOwnDevice;
    }
}
