<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Subcategory;

class OfferController extends Authorization
{
    public function __construct()
    {
        parent::__construct();
        $this->authenticated();
    }

    public function index(): void
    {
        $this->redirect(DIRPAGE);
    }

    public function suggest(): void
    {
        $this->setDir("Suggest");
        $this->setTitle("Sugira uma promoção | Humbleprice");
        $this->setDescription("Sugira uma oferta/promoção instingante de algum estabelecimento de nossa confiança.");
        $this->setKeywords("offer, suggest, low-price, price, discount");

        $this->renderLayout($this->getData());
    }

    public function publish(): ?bool
    {
        $offer = new Offer();
        $category = new Category();
        $subcategory = new Subcategory();

        if (isset($_POST["link"]) && isset($_POST["name"]) &&
            isset($_POST["old-price"]) && isset($_POST["new-price"]) &&
            isset($_POST["category"]) && isset($_POST["subcategory"]) &&
            isset($_FILES["picture"]) && isset($_POST["end-offer"])
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
            $endOffer = filter_input(
                INPUT_POST,
                "end-offer",
                FILTER_SANITIZE_SPECIAL_CHARS
            );

            if (! empty($link) && ! empty($name) &&
                ! empty($oldPrice) && ! empty($newPrice) &&
                ! empty($categorySlug) && ! empty($subcategorySlug) &&
                ! empty($picture) && ! empty($endOffer)
            ) {
                $oldPrice = floatval(str_replace(",",".", $oldPrice));
                $newPrice = floatval(str_replace(",",".", $newPrice));

                $categoryId = $category->getId("slug", $categorySlug);
                $subcategoryId = $subcategory->getId("slug", $subcategorySlug);

                if (! $subcategory->isChildOf(
                    $subcategoryId,
                    $categoryId,
                    "category")
                ) {
                    die("Esta subcategoria não pertence a respectiva categoria.");
                }

                $type = $picture["type"];

                if (in_array($type, ["image/jpeg", "image/png"])) {
                    $imageName = md5(time().rand(0, 99999))."jpg";
                    move_uploaded_file(
                        $picture["tmp_name"],
                        DIRREQ."public/img/products/{$imageName}"
                    );

                    list(
                        $originalWidth,
                        $originalHeight
                    ) = getimagesize(
                        DIRREQ."public/img/products/{$imageName}"
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
                            DIRREQ."public/img/products/{$imageName}"
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
                        DIRREQ."public/img/products/{$imageName}",
                        80
                    );

                    $info = [
                        "link" => $link,
                        "name" => $name,
                        "oldPrice" => $oldPrice,
                        "newPrice" => $newPrice,
                        "categoryId" => $categoryId,
                        "subcategoryId" => $subcategoryId,
                        "picture" => $imageName,
                        "endOffer" => $endOffer
                    ];

                    if ($offer->registerOffer($info)) {
                        return true;
                    }

                    die("Algo de errado ocorreu. Tente novamente mais tarde!");
                }

                die("A imagem deve ser do tipo JPEG, JPG ou PNG");
            }
        }

        die("Preencha todos os campos para continuar");
    }
}