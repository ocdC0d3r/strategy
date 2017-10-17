<?php
/**
 * Created by PhpStorm.
 * User: nenad
 * Date: 2/17/17
 * Time: 10:21 AM
 */

use App\Http\Controllers\PaymentController;
use App\Http\Services\LocalizationService;
use App\Http\Services\PaymentService;
use App\Http\Services\RegistrationService;
use App\Http\Services\SubscriptionService;
use App\Member;
use App\MembersRegistration;
use App\PartnerApplication;
use App\User;
use Faker\Factory;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @covers \App\Http\Controllers\PaymentController
 */
class PaymentControllerShouldTest extends TestCase
{
    use DatabaseTransactions;

    public $subscriptionService;
    public $registrationService;
    public $paymentService;
    public $localizationService;
    public $paymentController;
    public $token;
    public $faker;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();

        $this->localizationService = $this->getMockBuilder(LocalizationService::class)->getMock();
        $this->subscriptionService = $this->getMockBuilder(SubscriptionService::class)->getMock();
        $this->registrationService = $this->getMockBuilder(RegistrationService::class)
            ->setMethods([
                'createTempMember',
                'createLiteMember',
                'isLegalEntity',
                'upgradeToPremiumMember'
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentService = $this->getMockBuilder(PaymentService::class)
            ->setMethods([
                'getRestartPaymentData',
                'createPayment',
                'getPaymentData'
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentController = new PaymentController(
            $this->subscriptionService,
            $this->registrationService,
            $this->paymentService,
            $this->localizationService
        );

        $this->token = str_random(20);
        $this->faker = Factory::create();
    }

    /**
     * @test
     */
    public function initial_registration_step_with_invalid_parameters()
    {
        // Arrange
        $this->withoutMiddleware();

        $params = array(
            'inviterId' => '3',
            'memberTypeId' => '1',
            'token' => '',
            'firstname' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'lastname' => 'fffffffffffffffffffffffffffffffffff',
            'email' => 'zxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'password' => 'avenya',
            'password_confirmation' => 'avenya',
            'termsAndConditions' => 'accepted',
            'membership' => 'premium'
        );

        // Act
        $request = $this->call('POST', url('payment/token'), $params);

        // Assert
        $this->assertFalse(auth()->check());

        /**
         * Validation exception is not thrown, instead 302 (Found) status code is returned + redirection
         * In case validation is passed, 200 (Success) status code is returned
         */
        $this->assertEquals(302, $request->status());
        $this->assertTrue($request->isRedirection());
    }

    /**
     * @test
     */

    public function initial_registration_step_with_valid_parameters_for_lite_user()
    {
        // Arrange
        $params = array(
            'inviterId' => '3',
            'memberTypeId' => '1',
            'token' => $this->token,
            'firstname' => $this->faker->firstName,
            'lastname' => $this->faker->lastName,
            'email' => $this->faker->safeEmail,
            'password' => $this->token,
            'password_confirmation' => $this->token,
            'termsAndConditions' => 'accepted'
        );

        $this->registrationService->expects($this->any())
            ->method('createLiteMember')
            ->willReturn(random_int(999, 9999));

        // Act
        $request = $this->call('POST', url('payment/token'), $params);

        // Assert
        $this->assertFalse(auth()->check());
        $this->assertTrue(is_int($this->registrationService->createLiteMember($params)));
        $this->assertEquals(302, $request->getStatusCode());
    }

    /**
     * @test
     */
    public function initial_registration_step_with_valid_parameters_for_premium_member()
    {
        // Arrange
        $this->withoutMiddleware();

        $params = array(
            'inviterId' => '3',
            'memberTypeId' => '1',
            'token' => $this->token,
            'firstname' => 'Lorem',
            'lastname' => 'Ipsum',
            'email' => 'dolor.sit@amet.do',
            'password' => 'avenya',
            'password_confirmation' => 'avenya',
            'termsAndConditions' => 'accepted',
            'membership' => 'premium'
        );

        $this->registrationService->expects($this->once())
            ->method('createLiteMember')
            ->willReturn(true);

        // Act
        $request = $this->call('POST', url('payment/token'), $params);

        // Assert
        $this->assertFalse(auth()->check());

        $this->seeInDatabase('members_registration', [
            'token' => $this->token
        ]);

        $this->assertTrue($this->registrationService->createLiteMember($params));
        $this->assertEquals(200, $request->status());
    }

    /**
     * @test
     */
    public function attempt_to_change_membership_from_lite_to_premium_with_invalid_user_id()
    {
        // Arrange
        $exception = \Illuminate\Database\Eloquent\ModelNotFoundException::class;

        //Assert
        $this->expectException($exception);

        // Act
        $this->paymentController->changeToPremiumMembership(random_int(999, 9999));
    }

    /**
     * @test
     */
    public function changing_lite_membership_to_premium_with_valid_user_id()
    {
        // Arrange
        $this->withoutMiddleware();

        $existingLiteUser = factory(\App\User::class)->create();

        $this->paymentService->expects($this->once())
            ->method('getPaymentData')
            ->willReturn([
                'memberTypeId' => 1,
                'token' => $existingLiteUser->token,
                'email' => $existingLiteUser->email,
                'password' => $existingLiteUser->password,
                'upgradeMembership' => true,
            ]);

        $this->registrationService->expects($this->any())
            ->method('createTempMember')
            ->willReturn(true);

        $responseStub = new \GuzzleHttp\Handler\MockHandler([
            new Response(200, [
                'token' => $existingLiteUser->token
            ])
        ], null, null);

        $handler = HandlerStack::create($responseStub);
        $client = new Client(['handler' => $handler]);

        // Request uri does not matter as the HandlerStack class creates an identical response for any request
        $response = $client->request('/');

        $this->paymentService->expects($this->once())
            ->method('createPayment')
            ->willReturn($response);

        // Act
        $this->paymentController->changeToPremiumMembership($existingLiteUser->id);

        // Assert
        $this->assertNotEmpty(User::find($existingLiteUser->id));
        $this->assertFalse(MembersRegistration::where('email', $existingLiteUser->email)->exists());
    }

    /**
     * @test
     */
    public function restart_payment_when_user_exists_in_members_registration_table()
    {
        // Arrange
        $tempMember = factory(App\MembersRegistration::class)->create([
            'token' => $this->token
        ]);

        $this->paymentService->expects($this->any())
            ->method('getRestartPaymentData')
            ->will($this->returnValue([
                'memberTypeId' => $tempMember->member_type_id,
                'inviterId' => $tempMember->invited_by,
                'token' => $tempMember->token,
                'tokenPayment' => $tempMember->token_payment,
                'companyName' => $tempMember->company_name,
                'firstname' => $tempMember->firstname,
                'lastname' => $tempMember->lastname,
                'email' => $tempMember->email,
                'password' => $tempMember->password
            ]));

        $this->registrationService->expects($this->any())
            ->method('createTempMember')
            ->willReturn($tempMember);

        $responseStub = new \GuzzleHttp\Handler\MockHandler([
            new Response(200, [
                'token' => $tempMember->token
            ])
        ], null, null);

        $handler = HandlerStack::create($responseStub);
        $client = new Client(['handler' => $handler]);

        // Request uri does not matter as the HandlerStack class creates an identical response for any request
        $response = $client->request('/');

        $this->paymentService->expects($this->any())
            ->method('createPayment')
            ->willReturn($response);

        // Act
        $response = $this->paymentController->restartPayment($this->token);

        // Assert
        $this->assertFalse(MembersRegistration::where('email', $tempMember->email)->exists());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function attempt_to_restart_payment_when_user_not_in_members_registration_table()
    {
        // Arrange
        $tempMember = MembersRegistration::all();

        // Act
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->paymentController->restartPayment($this->token);

        // Assert
        $this->assertEmpty($tempMember);
    }

    /**
     * @test
     */
    public function non_valid_member_for_membership_upgrade()
    {
        // Arrange
        $tempMember = factory(MembersRegistration::class)->make();

        // Act
        $isPremium = $this->paymentController->upgradeMembership($tempMember);

        // Assert
        $this->assertFalse($isPremium);
    }

    /**
     * @test
     */
    public function valid_member_for_membership_upgrade()
    {
        // Arrange
        $tempMember = factory(MembersRegistration::class)->make([
            'upgrade_membership' => 1
        ]);

        // Act
        $isPremium = $this->paymentController->upgradeMembership($tempMember);

        // Assert
        $this->assertTrue($isPremium);
    }

    /**
     * @test
     */
    public function payment_token_should_be_created_if_not_passed()
    {
        // Arrange
        $tempMember = factory(MembersRegistration::class)->make();

        // Act
        $paymentToken = $this->paymentController->getTokenPayment($tempMember);

        // Assert
        $this->assertEquals(20, strlen($paymentToken));
        $this->assertNotEmpty($paymentToken);
    }

    /**
     * @test
     */
    public function status_is_success()
    {
        // Arrange
        $status = 'success';

        // Act
        $checkStatus = $this->paymentController->isSuccess($status);

        // Assert
        $this->assertTrue($checkStatus);
    }

    /**
     * @test
     */
    public function status_is_not_success()
    {
        // Arrange
        $status = 'SUccEsS';

        // Act
        $checkStatus = $this->paymentController->isSuccess($status);

        // Assert
        $this->assertFalse($checkStatus);
    }

    /**
     * @test
     */
    public function find_existing_user_for_success_process()
    {
        // Arrange
        $existingUser = factory(User::class)->create();

        // Act
        $findUser = $this->paymentController->getUserByToken($existingUser->token);

        // Assert
        $this->assertNotEmpty($findUser);
    }

    /**
     * @test
     */
    public function attempt_to_find_non_existing_user_for_success_process()
    {
        // Arrange
        $existingUser = factory(User::class)->make();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        // Act
        $findUser = $this->paymentController->getUserByToken($existingUser->token);

        // Assert
        $this->assertEmpty($findUser);
    }

    /**
     * @test
     */
    public function successful_registration_process_for_partner()
    {
        // Arrange
        $partner = factory(Member::class, 'partner')->create();

        $this->registrationService->expects($this->once())
            ->method('isLegalEntity')
            ->willReturn(true);

        // Act
        $response = $this->paymentController->success($partner->user->token);

        // Assert
        $this->assertEquals(2, $partner->member_type_id);
        $this->assertNull(MembersRegistration::where('token', $partner->user->token)->first());
        $this->assertTrue($response->isRedirection());
    }

    /**
     * @test
     */
    public function successful_registration_process_for_lite_member()
    {
        // Arrange
        $member = factory(Member::class)->create();

        // Act
        $response = $this->call('GET', url('payment/success', $member->user->token));

        // Assert
        $this->assertTrue($response->isRedirection());
        $this->assertEquals(1, $member->member_type_id);
        $this->assertNotEmpty(Member::where('user_id', $member->user->id)->first());
        $this->assertNull(MembersRegistration::where('token', $member->user->token)->first());
    }

    /**
     * @test
     */
    public function removing_user_data_from_members_registration_table()
    {
        // Arrange
        $userData = factory(MembersRegistration::class)->create();

        // Act
        $response = $this->paymentController->removeTemporaryDataForUser($userData->token);

        // Assert
        $this->assertNull(MembersRegistration::where('token', $userData->token)->first());
        $this->assertTrue($response->isRedirection());

    }

    /**
     * @test
     */
    public function attempt_to_remove_invalid_user_data_from_members_registration_table()
    {
        // Arrange
        $userData = factory(MembersRegistration::class)->make();

        // Act
        $response = $this->paymentController->removeTemporaryDataForUser($userData->token);

        // Assert
        $this->assertFalse($response);
    }

    /**
     * @test
     * @covers \App\Http\Controllers\PaymentController::callCreatePremiumUser()
     */
    public function call_create_premium_user()
    {
        // Arrange
        $partnerApplication = factory(\App\PartnerApplication::class)->create();
        $partnerApplication['free_partner'] = true;

        $tempMember = factory(MembersRegistration::class)->create([
            'firstname' => '',
            'lastname' => '',
            'company_name' => $partnerApplication['company_name'],
            'member_type_id' => 2,
            'company_type_id' => $partnerApplication['company_type_id'],
            'identification_number' => $partnerApplication['identification_number'],
            'date_of_establishment' => $partnerApplication['date_of_establishment'],
            'phone' => $partnerApplication['phone'],
            'street' => $partnerApplication['street'],
            'street_number' => $partnerApplication['street_number'],
            'city' => $partnerApplication['city'],
            'zip' => $partnerApplication['zip'],
            'longitude' => $partnerApplication['longitude'],
            'latitude' => $partnerApplication['latitude'],
            'company_introtext' => $partnerApplication['company_introtext'],
            'company_information' => $partnerApplication['company_information'],
            'company_url' => $partnerApplication['company_url'],
            'email' => $partnerApplication['email'],
            'password' => str_random(10),
            'token' => $partnerApplication['token'],
            'token_payment' => $partnerApplication['token_payment'],
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
            'invited_by' => 0,
            'upgrade_membership' => 0,
        ]);

        // Act
        $this->paymentController->callCreatePremiumUser($tempMember);

        // Assert
        $this->assertEquals(1, PartnerApplication::where('id', $partnerApplication->id)->pluck('paid')->first());
        $this->seeInDatabase('users', ['email' => $partnerApplication->email]);
    }
}