<?php
declare(strict_types=1);

namespace App\Common;

class Image
{
    /**
     * 剪切图片为圆形
     * @param string $picture [图片数据流：比如file_get_contents()返回的数据]
     * @return false|string
     */
    function toCircleImage(string $picture)
    {
        $src_img = imagecreatefromstring($picture);
        $w   = imagesx($src_img);
        $h   = imagesy($src_img);
        $w   = min($w, $h);
        $h   = $w;
        $img = imagecreatetruecolor($w, $h);
        //这一句一定要有
        imagesavealpha($img, true);
        //拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $bg);
        $r   = $w / 2; //圆半径
        $y_x = $r; //圆心X坐标
        $y_y = $r; //圆心Y坐标
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }

        // 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8"); 并且去掉缓存区函数
        // 获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng ( $img );
        imagedestroy($img);
        $contents =  ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    /**
     * png图片保存为透明底
     * @param string $srcPath
     * @param string $newPath
     * @return void
     */
    function toHyalineImage(string $srcPath, string $newPath): void
    {
        //获取源图gd图像标识符
        $srcImg = imagecreatefrompng($srcPath);
        //分配颜色 + alpha，将颜色填充到新图上
        $alpha = imagecolorallocatealpha($srcImg, 0, 0, 0, 127);
        imagefill($srcImg, 0, 0, $alpha);
        imagesavealpha($srcImg, true);
        imagepng($srcImg, $newPath);
    }

    /**
     * 在小程序码的中间区域镶嵌图片
     * @param $QR [小程序码数据流：比如file_get_contents()或者微信返回的数据]
     * @param $logo [中间显示图片的数据流：比如file_get_contents()返回的数据]
     * @return false|string
     */
    function qrcodeWithLogo($QR,$logo)
    {
        $QR   = imagecreatefromstring($QR);
        $logo = imagecreatefromstring($logo);
        $QR_width    = imagesx($QR); // 小程序码图片宽度
        $QR_height   = imagesy($QR); // 小程序码图片高度
        $logo_width  = imagesx($logo); // logo图片宽度
        $logo_height = imagesy($logo); // logo图片高度
        $logo_qr_width  = $QR_width / 2.2; // 组合之后logo的宽度(占二维码的1/2.2)
        $scale  = $logo_width / $logo_qr_width; // logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height / $scale; // 组合之后logo的高度
        $from_width = ($QR_width - $logo_qr_width) / 2; // 组合之后logo左上角所在坐标点
        // 重新组合图片并调整大小
        // imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
        imagecopyresampled($QR, $logo, (int)$from_width, (int)$from_width, 0, 0, (int)$logo_qr_width, (int)$logo_qr_height, $logo_width, $logo_height);

        // 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8"); 并且去掉缓存区函数
        // 获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng($QR);
        imagedestroy($QR);
        imagedestroy($logo);
        $contents =  ob_get_contents();
        ob_end_clean();
        return $contents;
    }

}


