<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Phantom Mask API",
 *     description="藥局口罩平台 API 文件",
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://phantom_mask_laradock.kdan.succ.work",
 *     description="Production API Server"
 * )
 * 
 * @OA\Tag(
 *     name="藥局",
 *     description="藥局相關 API"
 * )
 * 
 * @OA\Tag(
 *     name="口罩",
 *     description="口罩相關 API"
 * )
 * 
 * @OA\Tag(
 *     name="使用者",
 *     description="使用者相關 API"
 * )
 * 
 * @OA\Tag(
 *     name="交易",
 *     description="交易相關 API"
 * )
 */
class OpenApiSpec
{
} 