<?php
/**
 * Created by PhpStorm.
 * User: nenad
 * Date: 6/6/17
 * Time: 1:15 PM
 */

use App\Http\Controllers\MembersController;
use App\Http\Services\MemberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @covers \App\Http\Controllers\MembersController
 */
class MembersControllerShouldTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $memberService;

    /**
     * @var MembersController
     */
    private $membersController;

    /**
     * Parent constructor initialization
     */
    public function setUp()
    {
        parent::setUp();

        $this->memberService = $this->getMockBuilder(MemberService::class)->getMock();

        $this->membersController = new MembersController($this->memberService);
    }

    /**
     * @test
     * @covers MembersController::memberRegistrationLegalEntity()
     */
    public function registration_of_legal_entity_with_valid_data()
    {
        // Arrange
        $application = factory(\App\PartnerApplication::class)->create();

        // Act
        $response = $this->membersController->memberRegistrationLegalEntity($application->token);

        // Assert
        $this->assertTrue(is_a($response, \Illuminate\View\View::class));
        $this->assertSame($response->name(), 'auth.register');
    }

    /**
     * @test
     * @covers MembersController::memberRegistrationLegalEntity()
    */
    public function registration_of_legal_entity_with_existing_user()
    {
        // Arrange
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $application = factory(\App\PartnerApplication::class)->create();

        $existingUser = factory(\App\User::class)->create([
            'token' => $application->token
        ]);

        // Act
        $this->membersController->memberRegistrationLegalEntity($existingUser->token);
    }

    /**
     * @test
     * @covers MembersController::memberRegistrationLegalEntity()
     */
    public function registration_of_legal_entity_with_non_approved_application()
    {
        // Arrange
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $application = factory(\App\PartnerApplication::class)->create([
            'approved_by' => null,
            'approved_at' => null
        ]);

        // Act
        $this->membersController->memberRegistrationLegalEntity($application->token);
    }

    /**
     * @test
     * @covers MembersController::index()
    */
    public function loading_members_page()
    {
        // Act
        $response = $this->membersController->index();

        // Assert
        $this->assertTrue(is_a($response, \Illuminate\View\View::class));
        $this->assertSame($response->name(), 'pages.members.members');
    }
}