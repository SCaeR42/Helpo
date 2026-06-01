<?php

declare(strict_types=1);

namespace App\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * GraphQL Schema definition for Helpo API.
 */
class SchemaBuilder
{
    private array $services;

    public function __construct(array $services)
    {
        $this->services = $services;
    }

    /**
     * Build the GraphQL schema.
     *
     * @return Schema
     */
    public function build(): Schema
    {
        return new Schema([
            'query' => $this->buildQueryType(),
            'mutation' => $this->buildMutationType(),
        ]);
    }

    /**
     * Build Query type.
     *
     * @return ObjectType
     */
    private function buildQueryType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'myTickets' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->buildTicketType()))),
                    'description' => 'Get all tickets for the authenticated user',
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->getUserTickets($context['userId']);
                    },
                ],
                'ticket' => [
                    'type' => $this->buildTicketType(),
                    'description' => 'Get a single ticket by ID',
                    'args' => [
                        'id' => ['type' => Type::nonNull(Type::id())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->getTicketById((int) $args['id']);
                    },
                ],
                'ticketStatus' => [
                    'type' => Type::nonNull($this->buildTicketStatusType()),
                    'description' => 'Get the current status of a ticket',
                    'args' => [
                        'ticketId' => ['type' => Type::nonNull(Type::id())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->getTicketStatus((int) $args['ticketId']);
                    },
                ],
                'ticketMessages' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->buildMessageType()))),
                    'description' => 'Get all messages for a ticket',
                    'args' => [
                        'ticketId' => ['type' => Type::nonNull(Type::id())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['message']->getTicketMessages(
                            (int) $args['ticketId'],
                            $context['userId']
                        );
                    },
                ],
            ],
        ]);
    }

    /**
     * Build Mutation type.
     *
     * @return ObjectType
     */
    private function buildMutationType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'login' => [
                    'type' => Type::nonNull($this->buildAuthPayloadType()),
                    'description' => 'Authenticate user (auto-register if not exists)',
                    'args' => [
                        'input' => ['type' => Type::nonNull($this->buildLoginInputType())],
                    ],
                    'resolve' => function ($root, $args) {
                        return $this->services['auth']->login(
                            $args['input']['login'],
                            $args['input']['password']
                        );
                    },
                ],
                'createTicket' => [
                    'type' => Type::nonNull($this->buildTicketType()),
                    'description' => 'Create a new support ticket',
                    'args' => [
                        'input' => ['type' => Type::nonNull($this->buildCreateTicketInputType())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->createTicket(
                            $context['userId'],
                            $args['input']['subject'],
                            $args['input']['section'],
                            $args['input']['comment'] ?? null
                        );
                    },
                ],
                'sendMessage' => [
                    'type' => Type::nonNull($this->buildMessageType()),
                    'description' => 'Send a message to a ticket chat',
                    'args' => [
                        'input' => ['type' => Type::nonNull($this->buildCreateMessageInputType())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['message']->sendMessage(
                            (int) $args['input']['ticketId'],
                            $context['userId'],
                            $args['input']['content']
                        );
                    },
                ],
            ],
        ]);
    }

    /**
     * Build User type.
     *
     * @return ObjectType
     */
    private function buildUserType(): ObjectType
    {
        return new ObjectType([
            'name' => 'User',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())],
                'login' => ['type' => Type::nonNull(Type::string())],
                'createdAt' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);
    }

    /**
     * Build AuthPayload type.
     *
     * @return ObjectType
     */
    private function buildAuthPayloadType(): ObjectType
    {
        return new ObjectType([
            'name' => 'AuthPayload',
            'fields' => [
                'token' => ['type' => Type::nonNull(Type::string())],
                'user' => ['type' => Type::nonNull($this->buildUserType())],
            ],
        ]);
    }

    /**
     * Build LoginInput type.
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    private function buildLoginInputType(): \GraphQL\Type\Definition\InputObjectType
    {
        return new \GraphQL\Type\Definition\InputObjectType([
            'name' => 'LoginInput',
            'fields' => [
                'login' => ['type' => Type::nonNull(Type::string())],
                'password' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);
    }

    /**
     * Build Ticket type.
     *
     * @return ObjectType
     */
    private function buildTicketType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Ticket',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())],
                'userId' => ['type' => Type::nonNull(Type::id())],
                'subject' => ['type' => Type::nonNull(Type::string())],
                'section' => ['type' => Type::nonNull($this->buildTicketSectionEnum())],
                'comment' => ['type' => Type::string()],
                'statusCode' => ['type' => Type::nonNull(Type::string())],
                'statusName' => ['type' => Type::nonNull(Type::string())],
                'createdAt' => ['type' => Type::nonNull(Type::string())],
                'updatedAt' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);
    }

    /**
     * Build TicketSection enum.
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    private function buildTicketSectionEnum(): \GraphQL\Type\Definition\EnumType
    {
        return new \GraphQL\Type\Definition\EnumType([
            'name' => 'TicketSection',
            'values' => [
                'GENERAL' => ['value' => 'GENERAL'],
                'SUBSCRIPTION' => ['value' => 'SUBSCRIPTION'],
                'ACCOUNT' => ['value' => 'ACCOUNT'],
                'ERROR' => ['value' => 'ERROR'],
                'FEATURE' => ['value' => 'FEATURE'],
            ],
        ]);
    }

    /**
     * Build TicketStatus type.
     *
     * @return ObjectType
     */
    private function buildTicketStatusType(): ObjectType
    {
        return new ObjectType([
            'name' => 'TicketStatus',
            'fields' => [
                'code' => ['type' => Type::nonNull(Type::string())],
                'name' => ['type' => Type::nonNull(Type::string())],
                'message' => ['type' => Type::string()],
            ],
        ]);
    }

    /**
     * Build CreateTicketInput type.
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    private function buildCreateTicketInputType(): \GraphQL\Type\Definition\InputObjectType
    {
        return new \GraphQL\Type\Definition\InputObjectType([
            'name' => 'CreateTicketInput',
            'fields' => [
                'subject' => ['type' => Type::nonNull(Type::string())],
                'section' => ['type' => Type::nonNull($this->buildTicketSectionEnum())],
                'comment' => ['type' => Type::string()],
            ],
        ]);
    }

    /**
     * Build Message type.
     *
     * @return ObjectType
     */
    private function buildMessageType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Message',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())],
                'ticketId' => ['type' => Type::nonNull(Type::id())],
                'userId' => ['type' => Type::nonNull(Type::id())],
                'senderType' => ['type' => Type::nonNull($this->buildSenderTypeEnum())],
                'content' => ['type' => Type::nonNull(Type::string())],
                'statusCode' => ['type' => Type::string()],
                'statusName' => ['type' => Type::string()],
                'createdAt' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);
    }

    /**
     * Build SenderType enum.
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    private function buildSenderTypeEnum(): \GraphQL\Type\Definition\EnumType
    {
        return new \GraphQL\Type\Definition\EnumType([
            'name' => 'SenderType',
            'values' => [
                'USER' => ['value' => 'USER'],
                'SYSTEM' => ['value' => 'SYSTEM'],
            ],
        ]);
    }

    /**
     * Build CreateMessageInput type.
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    private function buildCreateMessageInputType(): \GraphQL\Type\Definition\InputObjectType
    {
        return new \GraphQL\Type\Definition\InputObjectType([
            'name' => 'CreateMessageInput',
            'fields' => [
                'ticketId' => ['type' => Type::nonNull(Type::id())],
                'content' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);
    }
}
