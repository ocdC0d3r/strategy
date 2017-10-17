<?php
/**
 * Created by PhpStorm.
 * User: nenad
 * Date: 6/5/17
 * Time: 9:05 AM
 */
use App\Http\Services\InvitationService;
use App\Http\Services\MailService;
use App\Invitation;
use App\Member;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @covers \App\Http\Services\InvitationService
 */
class InvitationServiceShouldTest extends TestCase
{
    use DatabaseTransactions;

    public $mailService;
    public $invitationService;

    public function setUp()
    {
        parent::setUp();

        $this->mailService = $this->getMockBuilder(MailService::class)->getMock();

        $this->invitationService = new InvitationService($this->mailService);
    }

    /**
     * @test
     * @covers InvitationService::getNonSuccessfulUserInvitations()
     */
    public function getting_unsuccessful_invitations_for_user()
    {
        // Arrange
        $invitations = factory(Invitation::class, 10)->create([
            'status' => 'pending'
        ]);

        // Act
        $response = $this->invitationService->getNonSuccessfulUserInvitations($invitations[0]->user->id)->get()->toArray();

        // Assert
        $this->assertNotEmpty($response);
        $this->assertTrue(is_array($response));
        $this->assertSame($response[0]['user_id'], $invitations[0]->user->id);
    }

    /**
     * @test
     * @covers InvitationService::getUserInvitations()
     */
    public function getting_invitations_for_user()
    {
        // Arrange
        $invitations = factory(Invitation::class, 10)->create();

        // Act
        $response = $this->invitationService->getUserInvitations($invitations[0]->user->id)->get()->toArray();

        // Assert
        $this->assertNotEmpty($response);
        $this->assertTrue(is_array($response));
        $this->assertSame($response[0]['user_id'], $invitations[0]->user->id);
    }

    /**
     * @test
     * @covers InvitationService::getUserInvitations()
     */
    public function attempt_to_get_non_existing_invitations_for_user()
    {
        // Arrange
        $user = factory(User::class)->create();

        // Act
        $response = $this->invitationService->getUserInvitations($user->id)->get()->toArray();

        // Assert
        $this->assertEmpty($response);
        $this->assertTrue(is_array($response));
    }

    /**
     * @test
     * @covers InvitationService::sendInvitation()
     */
    public function sending_new_invitation()
    {
        // Arrange
        $invitation = [
            'email' => 'test@tester.com',
            'name' => 'Lorem Ipsum',
        ];

        $member = factory(Member::class)->create();

        // Act
        $response = $this->invitationService->sendInvitation($member->user_id, $invitation, $member->user);

        // Assert
        $this->assertObjectHasAttribute('isSuccess', $response);
        $this->assertTrue($response->isSuccess);
    }

    /**
     * @test
     * @covers InvitationService::sendInvitation()
     */
    public function sending_invitation_with_invalid_email()
    {
        // Arrange
        $invitation = [
            'email' => str_random(20),
            'name' => 'Lorem Ipsum',
        ];

        $member = factory(Member::class)->create();

        // Act
        $response = $this->invitationService->sendInvitation($member->user_id, $invitation, $member->user);

        // Assert
        $this->assertObjectHasAttribute('isSuccess', $response);
        $this->assertObjectHasAttribute('errorMessage', $response);
        $this->assertFalse($response->isSuccess);
        $this->assertSame($response->errorMessage, 'Email is invalid.');
    }

    /**
     * @test
     * @covers InvitationService::sendInvitation()
     */
    public function attempt_to_send_existing_invitation()
    {
        // Arrange
        $member = factory(Member::class)->create();

        $existingInvitation = factory(Invitation::class)->create([
            'user_id' => $member->user->id
        ]);

        // Act
        $response = $this->invitationService->sendInvitation($member->user->id, $existingInvitation->toArray(), $member->user);

        // Assert
        $this->assertObjectHasAttribute('isSuccess', $response);
        $this->assertObjectHasAttribute('errorMessage', $response);
        $this->assertFalse($response->isSuccess);
        $this->assertSame($response->errorMessage, 'Du hast bereits eine Einladung an diese Email-Adresse geschickt.');
    }


    /**
     * @test
     * @covers InvitationService::resendInvitation()
     */
    public function resending_invitation()
    {
        // Arrange
        $member = factory(Member::class)->create();

        $existingInvitation = factory(Invitation::class)->create([
            'user_id' => $member->user->id
        ]);

        // Act
        $response = $this->invitationService->resendInvitation($member->user->id, $existingInvitation->toArray());

        // Assert
        $this->assertNull($response);
    }

    /**
     * @test
     * @covers InvitationService::logEmailReminderForEmail()
    */
    public function logging_newly_sent_email()
    {
        // Arrange
        $email = 'lorem.ipsum@dolor.amet';

        // Act
        $this->invitationService->logEmailReminderForEmail($email);

        // Assert
        $this->seeInDatabase('reminder_emails', ['email' => $email]);
    }

    /**
     * @test
    */
    public function logging_already_existing_email()
    {
        // Arrange
        $member = factory(Member::class)->create();

        $existingInvitation = factory(Invitation::class)->create([
            'user_id' => $member->user->id
        ]);

        // Act
        $this->invitationService->resendInvitation($member->user_id, $existingInvitation, $member->user);

        // Assert
        $this->assertSame(\App\ReminderEmail::where('email', $existingInvitation->email)->first()->created_at, \Carbon\Carbon::now()->toDateTimeString());
    }

    /**
     * @test
    */
    public function sending_bulk_invitations()
    {
        // Arrange
        $member = factory(Member::class)->create();

        $invitations = [
            0 => [
                'email' => 'lorem.ipsum@amet.et',
                'name' => 'Lorem Ipsum'
            ],
            1 => [
                'email' => 'consectetur@adipisicing.et',
                'name' => 'Dolor Sit'
            ],
            2 => [
                'email' => 'commodi.enim@amet.et',
                'name' => 'Amet Elit'
            ],
            3 => [
                'email' => 'optio.quis@amet.et',
                'name' => 'Neque Dolor'
            ],
        ];

        // Act
        $response = $this->invitationService->sendBulkInvitations($member->user->id, $invitations);

        // Assert
        $this->assertNull($response);
    }
}
