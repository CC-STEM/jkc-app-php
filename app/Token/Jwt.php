<?php
declare(strict_types=1);

namespace App\Token;

use App\Constants\ErrorCode;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

class Jwt
{
    /**
     * @var string
     */
    public string $claimKey = 'member';

    /**
     * token有效期
     * @var int
     */
    public int $expire = 90*24*3600;

    /**
     * 获取token
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public function getToken(array $params): string
    {
        $appDomain = env('APP_DOMAIN','');
        $jwt = json_decode(env('JWT'), true);
        $config = $this->getConfig($jwt);
        assert($config instanceof Configuration);
        $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));

        // 设置header和payload，以下的字段都可以自定义
        $token = $config->builder()->issuedBy($appDomain)// 发布者
        ->permittedFor($appDomain)// 接收者
        ->identifiedBy($jwt['identified'])// 对当前token设置的标识
        ->canOnlyBeUsedAfter($now)// 在这时间之后才能使用
        ->issuedAt($now)// token创建时间
        ->expiresAt($now->modify('+' . $this->expire . ' second'))// 过期时间
        ->withClaim($this->claimKey, $params)// 自定义数据
        ->getToken($config->signer(), $config->signingKey());

        // 获取加密后的token，转为字符串
        return $token->toString();
    }

    /**
     * @param string $tokenString
     * @return array
     * @throws \Exception
     */
    public function checkToken(string $tokenString): array
    {
        try {
            $appDomain = env('APP_DOMAIN','');
            $jwt = json_decode(env('JWT'), true);
            $config = $this->getConfig($jwt);
            assert($config instanceof Configuration);
            $token = $config->parser()->parse($tokenString);
            assert($token instanceof Plain);

            // 验证jwt id是否匹配
            $identifiedBy = new IdentifiedBy($jwt['identified']);
            // 验证签发人url是否正确
            $issuedBy = new IssuedBy($appDomain);
            // 验证客户端url是否匹配
            $permittedFor = new PermittedFor($appDomain);
            // 验证令牌是否已使用预期的签名者和密钥签名
            $signedWith = new SignedWith($config->signer(), $config->signingKey());
            // 验证过期等
            $strictValidAt = new StrictValidAt(new SystemClock(new \DateTimeZone(date_default_timezone_get())));

            $config->setValidationConstraints($identifiedBy, $issuedBy, $permittedFor, $signedWith, $strictValidAt);
            $constraints = $config->validationConstraints();
            $config->validator()->assert($token, ...$constraints);
        } catch (RequiredConstraintsViolated $e) {
            $violations = $e->violations();
            $violations = $violations[0];
            return ['code' => ErrorCode::FAILURE, 'msg' => $violations->getMessage(), 'data' => null];
        } catch (\Throwable $e) {
            return ['code' => ErrorCode::FAILURE, 'msg' => $e->getMessage(), 'data' => null];
        }
        $claimValue = $token->claims()->get($this->claimKey);
        return ['code' => ErrorCode::SUCCESS, 'msg' => ErrorCode::getMessage(ErrorCode::SUCCESS), 'data' => $claimValue];
    }

    /**
     * 配置秘钥加密
     * @param array $jwt
     * @return Configuration
     */
    private function getConfig(array $jwt): Configuration
    {
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::base64Encoded($jwt['secret']));

        return $configuration;
    }


}


