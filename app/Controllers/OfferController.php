<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Subcategory;
use App\Models\Like;
use App\Models\User;
use Cocur\Slugify\Slugify;

class OfferController extends Authorization
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->redirect(DIRPAGE);
    }

    public function view(string $slug = null): void
    {
        $offer = new Offer();
        $like = new Like();

        if (
            empty($slug)
            || ! $offerId = $offer->getId("slug", $slug)
        ) {
            $this->redirect(DIRPAGE);
        }

        if (! $offer->incrementViews($offerId)) {
            /**
             * @todo
             * Create log
             */
        }

        $offerData = $offer->getInfo("id", $offerId,
            [
                "users.name AS author",
                "categories.name AS category",
                "subcategories.name AS subcategory",
                "offers.slug",
                "offers.link",
                "offers.name",
                "offers.additional_info",
                "offers.old_price",
                "offers.new_price",
                "offers.published_at",
                "offers.end_offer",
                "offers.image",
                "offers.views",
                "offers.status"
            ],
            [
                ["users", "INNER"],
                ["categories", "INNER"],
                ["subcategories", "INNER"]
            ],
            [
                "offers.id_user = users.id",
                "offers.id_category = categories.id",
                "offers.id_subcategory = subcategories.id"
            ]
        );

        $this->setDir("Offer");
        $this->setTitle("{$offerData['name']} | Humbleprice");
        $this->setDescription("Encontre aqui o produto {$offerData['name']} no melhor preço possível.");
        $this->setKeywords("offer, low-price, price, discount");

        if (
            ! $this->hasPermission("MANAGE_OFFERS")
            && $offerData["status"] !== "approved"
        ) {
            $this->redirect(DIRPAGE);
        }

        $latestOffers = $offer->getRelatedOffers($offerId);
        $isClosed =
            $offerData["status"] === "closed"
            || $offerData["status"] === "refused"
            || ! empty($offerData["end_offer"])
            && date("Y-m-d") > $offerData["end_offer"];
        $likeCount = $like->count($offerId);
        $liked = ($this->authenticated())
            ? $like->liked($offerId, user()["id"])
            : false;

        $this->setData("offer", $offerData);
        $this->setData("relatedOffers", $latestOffers);
        $this->setData("isClosed", $isClosed);
        $this->setData("likes", $likeCount);
        $this->setData("liked", $liked);

        $this->renderLayout($this->getData());
    }

    public function suggest(): void
    {
        $this->authRequired();

        $this->setDir("Suggest");
        $this->setTitle("Sugira uma promoção | Humbleprice");
        $this->setDescription("Sugira uma oferta/promoção instingante de algum estabelecimento de nossa confiança.");
        $this->setKeywords("offer, suggest, low-price, price, discount");

        $this->renderLayout($this->getData());
    }

    public function publish(): void
    {
        $this->authRequired();

        $offer = new Offer();
        $category = new Category();
        $subcategory = new Subcategory();
        $user = new User();
        $slugify = new Slugify();

        if (
            isset($_POST["link"]) && isset($_POST["name"])
            && isset($_POST["old-price"]) && isset($_POST["new-price"])
            && isset($_POST["category"]) && isset($_POST["subcategory"])
            && isset($_FILES["picture"])
        ) {
            $link = filter_input(
                INPUT_POST,
                "link",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $name = filter_input(
                INPUT_POST,
                "name",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $slug = $slugify->Slugify($name);
            $oldPrice = filter_input(
                INPUT_POST,
                "old-price",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $newPrice = filter_input(
                INPUT_POST,
                "new-price",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $categorySlug = filter_input(
                INPUT_POST,
                "category",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $subcategorySlug = filter_input(
                INPUT_POST,
                "subcategory",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $picture = $_FILES["picture"];

            if (isset($_POST["additional-info"])) {
                $additionalInfo = filter_input(
                    INPUT_POST,
                    "additional-info",
                    FILTER_SANITIZE_SPECIAL_CHARS
                );
            }

            if (
                isset($_POST["end-offer"])
                && ! isset($_POST["offer-end-date-not-specified"])
            ) {
                $endOffer = filter_input(
                    INPUT_POST,
                    "end-offer",
                    FILTER_SANITIZE_SPECIAL_CHARS
                );
            }

            if (
                strlen($link) !== 0 && strlen($name) !== 0
                && strlen($oldPrice) !== 0 && strlen($newPrice) !== 0
                && strlen($categorySlug) !== 0 && strlen($subcategorySlug) !== 0
                && ! empty($_FILES["picture"])
            ) {
                $oldPrice = floatval(str_replace(",",".", $oldPrice));
                $newPrice = floatval(str_replace(",",".", $newPrice));

                $endOffer = (isset($endOffer) && strlen($endOffer) !== 0)
                        ? $endOffer
                        : null;

                $additionalInfo = (
                    isset($additionalInfo)
                    && strlen($additionalInfo) !== 0
                )
                    ? $additionalInfo
                    : null;

                $categoryId = $category->getId("slug", $categorySlug);

                if (! $categoryId) {
                    die("Uma categoria inválida foi irformada. Por favor, selecione outra.");
                }

                $subcategoryId = $subcategory->getId("slug", $subcategorySlug);

                if (! $subcategoryId) {
                    die("Uma subcategoria inválida foi irformada. Por favor, selecione outra.");
                }

                if (! $subcategory->isChildOf(
                    $subcategoryId,
                    $categoryId,
                    "category")
                ) {
                    die("Esta subcategoria não pertence a respectiva categoria.");
                }

                $imageName = $this->treatImage($picture);

                $status = "pending";

                if ($user->hasPermission(user()["id_role"], "MANAGE_QUEUE")) {
                    $status = "approved";
                }

                $info = [
                    "slug" => $slug,
                    "link" => $link,
                    "name" => $name,
                    "additionalInfo" => $additionalInfo,
                    "oldPrice" => $oldPrice,
                    "newPrice" => $newPrice,
                    "categoryId" => $categoryId,
                    "subcategoryId" => $subcategoryId,
                    "picture" => $imageName,
                    "endOffer" => $endOffer,
                    "status" => $status
                ];

                if ($offer->store($info)) {
                    die();
                }

                die("Algo de errado ocorreu. Tente novamente mais tarde!");
            }
        }

        die("Preencha todos os campos para continuar");
    }

    public function edit(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_OFFERS");

        $offer = new Offer();
        $category = new Category();

        if (
            empty($slug)
            || ! $offerId = $offer->getId("slug", $slug)
        ) {
            $this->redirect(DIRPAGE);
        }

        $offerData = $offer->getInfo("id", $offerId,
            [
                "id_category",
                "id_subcategory",
                "slug",
                "link",
                "name",
                "additional_info",
                "old_price",
                "new_price",
                "image",
                "end_offer"
            ]
        );

        $this->setDir("Edit");
        $this->setTitle("Editando {$offerData['name']} | Humbleprice");
        $this->setDescription("Edite a oferta {$offerData['name']} com as informações adequadas.");
        $this->setKeywords("ofertas, produtos, preço");

        $categoryData = $category->getInfo(
            "id",
            $offerData["id_category"],
            ["name", "slug"]
        );

        $this->setData("offer", $offerData);
        $this->setData("currentCategory", $categoryData);

        $this->renderLayout($this->getData());
    }

    public function update(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_OFFERS");

        $offer = new Offer();
        $category = new Category();
        $subcategory = new Subcategory();
        $slugify = new Slugify();

        if (
            empty($slug)
            || ! $offerId = $offer->getId("slug", $slug)
        ) {
            die("Esta oferta é inválida.");
        }

        if (
            isset($_POST["link"]) && isset($_POST["name"])
            && isset($_POST["old-price"]) && isset($_POST["new-price"])
            && isset($_POST["category"]) && isset($_POST["subcategory"])
        ) {
            $link = filter_input(
                INPUT_POST,
                "link",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $name = filter_input(
                INPUT_POST,
                "name",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $slug = $slugify->Slugify($name);
            $oldPrice = filter_input(
                INPUT_POST,
                "old-price",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $newPrice = filter_input(
                INPUT_POST,
                "new-price",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $categorySlug = filter_input(
                INPUT_POST,
                "category",
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            $subcategorySlug = filter_input(
                INPUT_POST,
                "subcategory",
                FILTER_SANITIZE_SPECIAL_CHARS
            );

            if (! empty($_FILES["picture"]["size"])) {
                $picture = $_FILES["picture"];
            }

            if (isset($_POST["additional-info"])) {
                $additionalInfo = filter_input(
                    INPUT_POST,
                    "additional-info",
                    FILTER_SANITIZE_SPECIAL_CHARS
                );
            }

            if (
                isset($_POST["end-offer"])
                && ! isset($_POST["offer-end-date-not-specified"])
            ) {
                $endOffer = filter_input(
                    INPUT_POST,
                    "end-offer",
                    FILTER_SANITIZE_SPECIAL_CHARS
                );
            }

            if (
                strlen($link) !== 0 && strlen($name) !== 0
                && strlen($oldPrice) !== 0 && strlen($newPrice) !== 0
                && strlen($categorySlug) !== 0 && strlen($subcategorySlug) !== 0
            ) {
                $oldPrice = floatval(str_replace(",", ".", $oldPrice));
                $newPrice = floatval(str_replace(",", ".", $newPrice));

                $endOffer = (isset($endOffer) && strlen($endOffer) !== 0)
                    ? $endOffer
                    : null;

                $additionalInfo = (
                    isset($additionalInfo)
                    && strlen($additionalInfo) !== 0
                )
                    ? $additionalInfo
                    : null;

                $categoryId = $category->getId("slug", $categorySlug);

                if (! $categoryId) {
                    die("Uma categoria inválida foi irformada. Por favor, selecione outra.");
                }

                $subcategoryId = $subcategory->getId("slug", $subcategorySlug);

                if (! $subcategoryId) {
                    die("Uma subcategoria inválida foi irformada. Por favor, selecione outra.");
                }

                if (!$subcategory->isChildOf(
                    $subcategoryId,
                    $categoryId,
                    "category")
                ) {
                    die("Esta subcategoria não pertence a respectiva categoria.");
                }

                if (isset($picture)) {
                    $imageName = $this->treatImage($picture);
                }

                $info = [
                    "offerId" => $offerId,
                    "slug" => $slug,
                    "link" => $link,
                    "name" => $name,
                    "additionalInfo" => $additionalInfo,
                    "oldPrice" => $oldPrice,
                    "newPrice" => $newPrice,
                    "categoryId" => $categoryId,
                    "subcategoryId" => $subcategoryId,
                    "endOffer" => $endOffer
                ];

                if (isset($imageName)) {
                    $info["picture"] = $imageName;
                }

                if ($offer->update($info)) {
                    die();
                }

                die("Algo de errado ocorreu. Tente novamente mais tarde!");
            }

            die("A imagem deve ser do tipo JPEG, JPG ou PNG");
        }

        die("Preencha todos os campos para continuar");
    }

    public function delete(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_OFFERS");

        $offer = new Offer();

        if (
            empty($slug)
            || ! $offerId = $offer->getId("slug", $slug)
        ) {
            die("Esta oferta é inválida.");
        }

        if ($offer->delete($offerId)) {
            die();
        }

        die("Não foi possível deletar esta oferta.");
    }

    public function subcategory(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_OFFERS");

        $offer = new Offer();
        $subcategory = new Subcategory();

        if (
            empty($slug)
            || ! $offerId = $offer->getId("slug", $slug)
        ) {
            die();
        }

        $subcategoryId = $offer->getInfo(
            "id",
            $offerId,
            ["id_subcategory"]
        )["id_subcategory"];

        die($subcategory->getInfo("id", $subcategoryId, ["slug"])["slug"]);
    }


    public function approve(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_QUEUE");

        if ($this->setStatus("approved", $slug)) {
            die();
        }

        die("Não foi possível aprovar essa oferta.");
    }

    public function refuse(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_QUEUE");

        if ($this->setStatus("refused", $slug)) {
            die();
        }

        die("Não foi possível recusar essa oferta.");
    }

    public function close(string $slug = null): void
    {
        $this->authRequired()->withPermission("MANAGE_OFFERS");

        if ($this->setStatus("closed", $slug)) {
            die();
        }

        die("Não foi possível fechar essa oferta.");
    }

    private function setStatus(string $status, string $slug = null): void
    {
        $offer = new Offer();

        if (
            empty($slug)
            || ! $offerId = $offer->getId("slug", $slug)
        ) {
            die("Esta oferta é inválida.");
        }

        if ($offer->updateStatus($offerId, $status)) {
            die();
        }

        die("Não foi possível alterar o status desta oferta.");
    }

    private function treatImage(array $picture): ?string
    {
        $type = $picture["type"];

        if (in_array($type, ["image/jpeg", "image/png"])) {
            $imageName = md5(time() . rand(0, 99999)) . ".jpg";
            move_uploaded_file(
                $picture["tmp_name"],
                DIRREQ . "public/img/products/{$imageName}"
            );

            list(
                $originalWidth,
                $originalHeight
                ) = getimagesize(
                DIRREQ . "public/img/products/{$imageName}"
            );

            $ratio = $originalWidth / $originalHeight;

            $width = 500;
            $height = 500;

            if ($width / $height > $ratio) {
                $width = $height * $ratio;
            } else {
                $height = $width / $ratio;
            }

            $img = imagecreatetruecolor($width, $height);

            if ($type == "image/jpeg") {
                $original = imagecreatefromjpeg(
                    DIRREQ . "public/img/products/{$imageName}"
                );
            } elseif ($type == "image/png") {
                $original = imagecreatefrompng(
                    DIRREQ . "public/img/products/{$imageName}"
                );
            } else {
                die("A imagem deve ser do tipo JPEG, JPG ou PNG");
            }

            imagecopyresampled(
                $img,
                $original,
                0,
                0,
                0,
                0,
                $width,
                $height,
                $originalWidth,
                $originalHeight
            );

            imagejpeg(
                $img,
                DIRREQ . "public/img/products/{$imageName}",
                80
            );

            return $imageName;
        }

        die("A imagem deve ser do tipo JPEG, JPG ou PNG");
    }
}