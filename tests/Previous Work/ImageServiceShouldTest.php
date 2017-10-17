<?php
/**
 * Created by PhpStorm.
 * User: nenad
 * Date: 6/14/17
 * Time: 2:32 PM
 */

use App\Articles;
use App\Http\Services\ImageService;
use App\Http\Services\PartnerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImageServiceShouldTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var string $image
     */
    private $image;

    /**
     * @var ImageService
     */
    private $imageService;

    /**
     * @var array $imageExtensions
     */
    private $imageExtensions;

    /**
     * @var PartnerService
     */
    private $partnerService;

    /**
     * Parent constructor initialization
     */
    public function setUp()
    {
        parent::setUp();

        // Valid Image
        $pathToValidImage = resource_path('tests/images/offer.jpeg');
        $type = pathinfo($pathToValidImage, PATHINFO_EXTENSION);
        $data = file_get_contents($pathToValidImage);
        $this->image = 'data:image/' . $type . ';base64,' . base64_encode($data);

        $this->imageExtensions = ['jpeg', 'png', 'jpg'];
        $this->imageService = new ImageService;
        $this->partnerService = new PartnerService;
    }

    /**
     * @test
     * @covers ImageService::uploadBase64ImageToFilesystem()
     */
    public function uploading_image_to_filesystem()
    {
        // Arrange
        $randomInt = random_int(999, 9999);

        // Act
        $this->imageService->uploadBase64ImageToFilesystem($this->image, "test-upload-{$randomInt}");

        // Assert
        $isUploaded = Storage::disk('local')->exists("test-upload-{$randomInt}.jpeg");
        $this->assertTrue($isUploaded);
    }

    /**
     * @test
     * @covers ImageService::uploadBase64ImageToDb()
    */
    public function uploading_profile_image_to_database(): void
    {
        // Arrange
        $memberId = factory(\App\Member::class)->create()->id;

        // Act
        $this->imageService->uploadBase64ImageToDb($this->image, $memberId);

        // Assert
        $this->seeInDatabase('members', [
            'id' => $memberId,
            'profile_image' => $this->image
        ]);
    }

    /**
     * @test
     * @covers ImageService::uploadBase64ImageToDb()
    */
    public function uploading_company_logo_to_database(): void
    {
        // Arrange
        $partner = factory(\App\Member::class, 'partner')->create();

        DB::table('users_roles')->insert([
            ['user_id' => $partner->user->id, 'role_id' => 2],
            ['user_id' => $partner->user->id, 'role_id' => 3]
        ]);

        // Act
        $this->imageService->uploadBase64ImageToDb($this->image, $partner->id);

        // Assert
        $this->seeInDatabase('members', [
            'id' => $partner->id,
            'profile_image' => NULL,
            'logo' => $this->image
        ]);
    }

    /**
     * @test
     * @covers ImageService::getArticleImageName()
    */
    public function getting_article_image_name()
    {
        // Arrange
        $article = Articles::inRandomOrder()->first();

        // Act
        $response = $this->imageService->getArticleImageName($article->id);
        $fileInfo = pathinfo($response);

        // Assert
        $this->assertTrue(in_array($fileInfo['extension'], $this->imageExtensions, true));
        $this->assertSame("article-{$article->image[0]->id}", $fileInfo['filename']);
    }

    /**
     * @test
     * @covers ImageService::getOfferImageName()
     */
    public function getting_offer_image_name(): void
    {
        // Arrange
        $offer = DB::table('offer_images')
            ->select('offer_id', 'image_id')
            ->inRandomOrder()
            ->first();

        $allOfferImages = DB::table('offer_images')
            ->where('offer_id', $offer->offer_id)
            ->select('image_id')
            ->get();

        $imageIds = [];

        foreach ($allOfferImages as $offerImage){
            $imageIds[] = $offerImage->image_id;
        }

        // Act
        $response = $this->imageService->getOfferImageName($offer->image_id);
        $fileInfo = pathinfo($response);

        // Assert
        $this->assertTrue(in_array($fileInfo['extension'], $this->imageExtensions, true));
        $this->assertTrue(in_array($offer->image_id, $imageIds, true));
    }

    /**
     * @test
     * @covers ImageService::getPartnerImageName()
    */
    public function getting_partner_logo_name(): void
    {
        // Arrange
        $partnersIds = $this->partnerService->getAllPartners()->inRandomOrder()->pluck('id');
        $validPartnerId = $partnersIds[random_int(0, $partnersIds->count() - 1)];

        // Act
        $response = $this->imageService->getPartnerImageName($validPartnerId);
        $fileInfo = pathinfo($response);

        // Assert
        $this->assertTrue(in_array($fileInfo['extension'], $this->imageExtensions, true));
        $this->assertSame("partner-logo-{$validPartnerId}", $fileInfo['filename']);
    }

    /**
     * @test
     * @covers ImageService::validateBase64ImageMimeSizeAndDimensions()
    */
    public function validating_image_with_invalid_ratio(): void
    {
        // Arrange
        $pathToInvalidImage = resource_path('tests/images/invalidOffer.jpg');
        $type = pathinfo($pathToInvalidImage, PATHINFO_EXTENSION);
        $data = file_get_contents($pathToInvalidImage);
        $invalidImage = "data:image/{$type};base64," . base64_encode($data);

        // Act
        $response = $this->imageService->validateBase64ImageMimeSizeAndDimensions($invalidImage);

        // Assert
        $this->assertSame('Das Bild hat ein ungültiges Seitenverhältnis.', $response); // Invalid Ratio
    }

    /**
     * @test
     * @covers ImageService::validateBase64ImageMimeSizeAndDimensions()
     */
    public function validating_image_with_invalid_mime(): void
    {
        // Arrange
        $pathToInvalidImage = resource_path('tests/images/mime.gif');
        $type = pathinfo($pathToInvalidImage, PATHINFO_EXTENSION);
        $data = file_get_contents($pathToInvalidImage);
        $invalidImage = "data:image/{$type};base64," . base64_encode($data);

        // Act
        $response = $this->imageService->validateBase64ImageMimeSizeAndDimensions($invalidImage);

        // Assert
       $this->assertSame('Das Bild hat einen falschen Typ.', $response); // Invalid Mime type
    }

    /**
     * @test
     * @covers ImageService::validateBase64ImageMimeSizeAndDimensions()
     */
    public function validating_image_with_invalid_size(): void
    {
        // Arrange
        $pathToInvalidImage = resource_path('tests/images/big-image.JPG');
        $type = pathinfo($pathToInvalidImage, PATHINFO_EXTENSION);
        $data = file_get_contents($pathToInvalidImage);
        $invalidImage = "data:image/{$type};base64," . base64_encode($data);

        // Act
        $response = $this->imageService->validateBase64ImageMimeSizeAndDimensions($invalidImage);

        // Assert
        $this->assertSame('Das Bild ist zu gross.', $response); // Invalid Size
    }

    /**
     * @test
     * @covers ImageService::validateBase64ImageMimeSizeAndDimensions()
     */
    public function validating_image_with_invalid_dimensions(): void
    {
        // Arrange
        $pathToInvalidImage = resource_path('tests/images/invalid-dimensions.jpg');
        $type = pathinfo($pathToInvalidImage, PATHINFO_EXTENSION);
        $data = file_get_contents($pathToInvalidImage);
        $invalidImage = "data:image/{$type};base64," . base64_encode($data);

        // Act
        $response = $this->imageService->validateBase64ImageMimeSizeAndDimensions($invalidImage);

        // Assert
        $this->assertSame('Die Dimensionen des Bildes sind ungültig.', $response); // Invalid Dimensions
    }
}