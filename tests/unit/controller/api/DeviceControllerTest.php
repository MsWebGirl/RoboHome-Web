<?php

namespace Tests\Unit\Controller\Api;

use App\Http\Authentication\ILoginAuthenticator;
use App\Http\Globals\DeviceActions;
use App\User;
use Mockery;
use Tests\Unit\Controller\Common\DeviceControllerTestCase;

class DeviceControllerTest extends DeviceControllerTestCase
{
    private $messageId;

    public function setUp()
    {
        parent::setUp();

        $this->messageId = self::$faker->uuid;
    }

    public function testIndex_GivenUserExistsWithNoDevices_ReturnsJsonResponse()
    {
        $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();

        $response = $this->callDevices();

        $this->assertDiscoverAppliancesResponseWithoutDevice($response);
    }

    public function testIndex_GivenUserExistsWithDevices_ReturnsJsonResponse()
    {
        $device1Name = self::$faker->word();
        $device2Name = self::$faker->word();
        $device3Name = self::$faker->word();

        $this->givenSingleUserExistsWithDevicesRegisteredWithApi($device1Name, $device2Name, $device3Name);

        $response = $this->callDevices();

        $this->assertDiscoverAppliancesResponse($response, $device1Name, $device2Name, $device3Name);
    }

    public function testIndex_GivenUserDoesNotExist_Returns401()
    {
        $response = $this->getJson('/api/devices', [
            'HTTP_Authorization' => 'Bearer ' . self::$faker->uuid(),
            'HTTP_Message_Id' => $this->messageId
        ]);

        $response->assertStatus(401);
    }

    public function testTurnOn_GivenUserExistsWithDevice_ReturnsJsonResponse()
    {
        $user = $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();
        $device = $this->createDevice(self::$faker->word(), $user);

        $this->givenDeviceIsRegisteredToUser($device, $user->user_id);

        $response = $this->callControl(DeviceActions::TURN_ON, $device->id);

        $this->assertControlConfirmation($response);
    }

    public function testTurnOn_GivenUserExistsWithDevice_CallsPublish()
    {
        $user = $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();
        $device = $this->createDevice(self::$faker->word(), $user);

        $this->mockMessagePublisher();
        $this->givenDeviceIsRegisteredToUser($device, $user->user_id);

        $this->callControl(DeviceActions::TURN_ON, $device->id);
    }

    public function testTurnOn_GivenUserExistsWithNoDevices_Returns401()
    {
        $user = $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();
        $deviceId = self::$faker->randomDigit();

        $this->givenDoesUserOwnDevice($user, $deviceId, false);

        $response = $this->callControl(DeviceActions::TURN_ON, $deviceId);

        $response->assertStatus(401);
    }

    public function testTurnOff_GivenUserExistsWithDevice_ReturnsJsonResponse()
    {
        $user = $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();
        $device = $this->createDevice(self::$faker->word(), $user);

        $this->givenDeviceIsRegisteredToUser($device, $user->user_id);

        $response = $this->callControl(DeviceActions::TURN_OFF, $device->id);

        $this->assertControlConfirmation($response);
    }

    public function testTurnOff_GivenUserExistsWithDevice_CallsPublish()
    {
        $user = $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();
        $device = $this->createDevice(self::$faker->word(), $user);

        $this->mockMessagePublisher();
        $this->givenDeviceIsRegisteredToUser($device, $user->user_id);

        $this->callControl(DeviceActions::TURN_OFF, $device->id);
    }

    public function testTurnOff_GivenUserExistsWithNoDevices_Returns401()
    {
        $user = $this->givenSingleUserExistsWithNoDevicesRegisteredWithApi();
        $deviceId = self::$faker->randomDigit();

        $this->givenDoesUserOwnDevice($user, $deviceId, false);

        $response = $this->callControl(DeviceActions::TURN_OFF, $deviceId);

        $response->assertStatus(401);
    }

    private function givenSingleUserExistsWithNoDevicesRegisteredWithApi()
    {
        $user = $this->givenSingleUserExists();

        $this->registerUserWithApi($user);

        $mockUserTable = Mockery::mock(User::class);
        $mockUserTable
            ->shouldReceive('where')->with('user_id', $user->user_id)->andReturn(Mockery::self())
            ->shouldReceive('first')->andReturn(Mockery::self())
            ->shouldReceive('getAttribute')->with('devices')->andReturn([]);

        $this->app->instance(User::class, $mockUserTable);

        return $user;
    }

    private function givenSingleUserExistsWithDevicesRegisteredWithApi($device1Name, $device2Name, $device3Name)
    {
        $user = $this->givenSingleUserExistsWithDevices($device1Name, $device2Name, $device3Name);

        $this->registerUserWithApi($user);
    }

    private function registerUserWithApi(User $user)
    {
        $mockRequest = Mockery::mock(ILoginAuthenticator::class);
        $mockRequest->shouldReceive('processApiLoginRequest')->withAnyArgs()->once()->andReturn($user);
        $this->app->instance(ILoginAuthenticator::class, $mockRequest);
    }

    private function callDevices()
    {
        $response = $this->getJson('/api/devices', [
            'HTTP_Authorization' => 'Bearer ' . self::$faker->uuid(),
            'HTTP_Message_Id' => $this->messageId
        ]);

        return $response;
    }

    private function callControl($action, $deviceId)
    {
        $urlValidAction = strtolower($action);

        $response = $this->postJson('/api/devices/' . $urlValidAction, ['id' => $deviceId], [
            'HTTP_Authorization' => 'Bearer ' . self::$faker->uuid(),
            'HTTP_Message_Id' => $this->messageId
        ]);

        return $response;
    }

    private function assertDiscoverAppliancesResponseWithoutDevice($response)
    {
        $response->assertJsonStructure([
            'header' => [
                'messageId',
                'name',
                'namespace',
                'payloadVersion'
            ],
            'payload' => [
                'discoveredAppliances' => []
            ]
        ]);

        $response->assertSee($this->messageId);
    }

    private function assertDiscoverAppliancesResponse($response, $device1Name, $device2Name, $device3Name)
    {
        $response->assertJsonStructure([
            'header' => [
                'messageId',
                'name',
                'namespace',
                'payloadVersion'
            ],
            'payload' => [
                'discoveredAppliances' => [
                    [
                        'actions',
                        'additionalApplianceDetails',
                        'applianceId',
                        'friendlyName',
                        'friendlyDescription',
                        'isReachable',
                        'manufacturerName',
                        'modelName',
                        'version'
                    ],
                    [
                        'actions',
                        'additionalApplianceDetails',
                        'applianceId',
                        'friendlyName',
                        'friendlyDescription',
                        'isReachable',
                        'manufacturerName',
                        'modelName',
                        'version'
                    ],
                    [
                        'actions',
                        'additionalApplianceDetails',
                        'applianceId',
                        'friendlyName',
                        'friendlyDescription',
                        'isReachable',
                        'manufacturerName',
                        'modelName',
                        'version'
                    ]
                ]
            ]
        ]);

        $response->assertSee($this->messageId);
        $response->assertSee($device1Name);
        $response->assertSee($device2Name);
        $response->assertSee($device3Name);
    }

    private function assertControlConfirmation($response)
    {
        $response->assertJsonStructure([
            'header' => [
                'messageId',
                'name',
                'namespace',
                'payloadVersion'
            ],
            'payload' => []
        ]);

        $response->assertSee($this->messageId);
    }
}
