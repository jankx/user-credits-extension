<?php

namespace Jankx\Extensions\UserCredits\Credit;

use Jankx\Extensions\UserCredits\CreditType\CreditType;
use Jankx\Extensions\UserCredits\PostTypes\CreditTransactionPostType;
use RuntimeException;

final class PostTypeCreditTransactionRepository implements CreditTransactionRepositoryInterface
{
    public const META_ACTION = '_credit_type';

    public const META_WALLET = '_credit_wallet';

    public const META_AMOUNT = '_credit_amount';

    public const META_BALANCE_AFTER = '_credit_balance_after';

    public const META_USER = '_credit_user_id';

    public const META_NOTE = '_credit_note';

    public function record(
        int $userId,
        CreditType $type,
        string $action,
        float $amount,
        float $balanceAfter,
        string $note = '',
        string $title = ''
    ): CreditTransaction {
        $postId = wp_insert_post([
            'post_type' => CreditTransactionPostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title !== '' ? $title : $this->buildTitle($action, $amount, $type),
            'meta_input' => [
                self::META_ACTION => $action,
                self::META_WALLET => $type->getId(),
                self::META_AMOUNT => $amount,
                self::META_BALANCE_AFTER => $balanceAfter,
                self::META_USER => $userId,
                self::META_NOTE => $note,
            ],
        ], true);

        if (is_wp_error($postId) || !$postId) {
            throw new RuntimeException('Unable to persist the credit transaction.');
        }

        $post = get_post($postId);

        return new CreditTransaction(
            (int) $postId,
            $userId,
            $type->getId(),
            $action,
            $amount,
            $balanceAfter,
            $note,
            $post instanceof \WP_Post ? (string) $post->post_date : current_time('mysql'),
            $post instanceof \WP_Post ? (string) $post->post_title : $title
        );
    }

    public function findByUser(int $userId, int $limit = 20, ?string $typeId = null): array
    {
        $query = new \WP_Query($this->buildQueryArgs($userId, $limit, $typeId));

        return array_map(function (\WP_Post $post): CreditTransaction {
            return $this->mapPost($post);
        }, $query->posts);
    }

    public function countByUser(int $userId, ?string $typeId = null): int
    {
        $args = $this->buildQueryArgs($userId, 1, $typeId);
        $args['fields'] = 'ids';
        $args['posts_per_page'] = 1;

        $query = new \WP_Query($args);

        return (int) $query->found_posts;
    }

    private function buildQueryArgs(int $userId, int $limit, ?string $typeId): array
    {
        $metaQuery = [
            [
                'key' => self::META_USER,
                'value' => $userId,
                'compare' => '=',
            ],
        ];

        if ($typeId !== null && $typeId !== '') {
            $metaQuery[] = [
                'key' => self::META_WALLET,
                'value' => $typeId,
                'compare' => '=',
            ];
        }

        return [
            'post_type' => CreditTransactionPostType::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => $limit,
            'meta_query' => $metaQuery,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
    }

    private function mapPost(\WP_Post $post): CreditTransaction
    {
        return new CreditTransaction(
            (int) $post->ID,
            (int) get_post_meta($post->ID, self::META_USER, true),
            (string) get_post_meta($post->ID, self::META_WALLET, true),
            (string) get_post_meta($post->ID, self::META_ACTION, true),
            (float) get_post_meta($post->ID, self::META_AMOUNT, true),
            (float) get_post_meta($post->ID, self::META_BALANCE_AFTER, true),
            (string) get_post_meta($post->ID, self::META_NOTE, true),
            (string) $post->post_date,
            (string) $post->post_title
        );
    }

    private function buildTitle(string $action, float $amount, CreditType $type): string
    {
        return sprintf(
            '%s %s',
            CreditTransactionAction::label($action),
            $type->format($amount)
        );
    }
}
