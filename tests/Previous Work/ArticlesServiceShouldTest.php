<?php
/**
 * Created by PhpStorm.
 * User: nenad
 * Date: 6/13/17
 * Time: 9:04 AM
 */

use App\Articles;
use App\Http\Services\ArticlesService;
use App\Http\Services\ImageService;
use App\User;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;

/**
 * Class ArticlesServiceShouldTest
 * @covers \App\Http\Services\ArticlesService
 */
class ArticlesServiceShouldTest extends TestCase
{
    use DatabaseTransactions;

    private $imageService;
    private $articleService;
    public $faker;

    /**
     * Parent constructor initialization
     */
    public function setUp()
    {
        parent::setUp();

        $this->imageService = $this->getMockBuilder(ImageService::class)->getMock();
        $this->articleService = new ArticlesService($this->imageService);

        $this->faker = Factory::create();
    }

    /**
     * @test
     * @covers ArticlesService::create()
     */
    public function creating_article_as_admin(): void
    {
        // login user who is also admin
        $user = User::find(3);
        Auth::login($user);

        // Valid Image
        $path = resource_path('tests/images/offer.jpeg');
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $validOfferImage = "data:image/{$type};base64," . base64_encode($data);

        // Arrange
        $article = [
            'title' => $this->faker->sentence,
            'introText' => $this->faker->sentence,
            'text' => $this->faker->paragraph,
        ];

        $categories = [
            random_int(1, 5),
            random_int(6, 10)
        ];

        $tags = [
            random_int(1, 5),
            random_int(6, 10)
        ];

        $image = [
            'description' => $this->faker->sentence,
            'image' => $validOfferImage
        ];

        // Act
        $this->articleService->create($article, $categories, $tags, $image);

        // Assert
        $this->seeInDatabase('articles', [
            'title' => $article['title'],
            'introtext' => $article['introText'],
            'text' => $article['text']
        ]);

        $created = Articles::where('title', $article['title'])
            ->where('member_id', Auth::user()->id)
            ->first();

        $this->assertNotEmpty($created);
        $this->assertNotNull($created->approved_by);
    }

    /**
     * @test
     * @covers ArticlesService::create()
     */
    public function creating_article_as_member(): void
    {
        // login user who is also lite member
        $user = User::whereHas('roles', function ($query) {
            $query->where('role', 'lite');
        })->first();

        Auth::login($user);

        // Valid Image
        $path = resource_path('tests/images/offer.jpeg');
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $validOfferImage = "data:image/{$type};base64," . base64_encode($data);

        // Arrange
        $article = [
            'title' => $this->faker->sentence,
            'introText' => $this->faker->sentence,
            'text' => $this->faker->paragraph,
        ];

        $categories = [
            random_int(1, 5),
            random_int(6, 10)
        ];

        $tags = [
            random_int(1, 5),
            random_int(6, 10)
        ];

        $image = [
            'description' => $this->faker->sentence,
            'image' => $validOfferImage
        ];

        // Act
        $this->articleService->create($article, $categories, $tags, $image);

        // Assert
        $this->seeInDatabase('articles', [
            'title' => $article['title'],
            'introtext' => $article['introText'],
            'text' => $article['text'],
            'member_id' => $user->id
        ]);

        $created = Articles::where('title', $article['title'])
            ->where('member_id', auth()->user()->id)
            ->first();

        $this->assertNull($created->approved_by);
        $this->assertNull($created->approved_at);
    }

    /**
     * @test
     * @covers ArticlesService::getAllArticles()
    */
    public function getting_all_articles(): void
    {
        // Act
        $allArticles = $this->articleService->getAllArticles()->get();

        // Assert
        $this->assertTrue($allArticles->count() > 0);
        $this->assertNotEmpty($allArticles);
    }

    /**
     * @test
     * @covers ArticlesService::getArticleForReview()
    */
    public function getting_soft_deleted_article_for_review()
    {
        // Arrange
        $user = User::find(3);
        Auth::login($user);

        $article = factory(Articles::class)->create([
            'deleted_at' => date('Y-m-d')
        ]);

        // Act
        $response = $this->articleService->getArticleForReview($article->slug);

        // Assert
        $this->assertNotEmpty($response);
    }

    /**
     * @test
     * @covers ArticlesService::getCommentsForArticle()
    */
    public function getting_comments_for_article()
    {
        // Arrange
        $user = User::find(3);
        Auth::login($user);

        $articleWithComment = factory(\App\Comment::class)->create([
            'approved_by' => $user->id,
            'approved_at' => date('Y-m-d')
        ]);

        // Act
        $response = $this->articleService->getCommentsForArticle($articleWithComment->article_id)->get();

        // Assert
        $this->assertNotEmpty($response);
    }

    /**
     * @test
     * @covers ArticlesService::getCommentsForArticle()
     */
    public function getting_comments_count_for_article()
    {
        // Arrange
        $user = User::find(3);
        Auth::login($user);

        $articleWithComment = factory(\App\Comment::class)->create([
            'approved_by' => $user->id,
            'approved_at' => date('Y-m-d')
        ]);

        // Act
        $count = $this->articleService->getCommentCountForArticle($articleWithComment->article_id);

        // Assert
        $this->assertSame(1, $count);
    }

    /**
     * @test
     * @covers ArticlesService::getNextOrPreviousArticle()
    */
    public function getting_next_article()
    {
        // Arrange
        $next = 'next';
        $createdAt = \Carbon\Carbon::now()->setDate(1975, 5, 21)->format('Y-m-d');

        // Act
        $response = $this->articleService->getNextOrPreviousArticle($createdAt, $next);

        // Assert
        $this->assertNotEmpty($response);
    }

    /**
     * @test
     * @covers ArticlesService::getNextOrPreviousArticle()
    */
    public function getting_previous_article()
    {
        // Arrange
        $previous = 'previous';
        $createdAt = date('Y-m-d');

        // Act
        $response = $this->articleService->getNextOrPreviousArticle($createdAt, $previous);

        // Assert
        $this->assertNotEmpty($response);
    }
}