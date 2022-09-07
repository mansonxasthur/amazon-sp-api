<?php

namespace App\Services\Amazon\Facades;

use App\Repositories\EventRepository;
use App\Repositories\OrderStateRepository;

class OrderStateService
{
    protected array $hashedOrders;
    protected array $createdOrders = [];
    protected array $updatedOrders = [];

    public function __construct(protected OrderStateRepository $orderRepository, protected EventRepository $eventRepository)
    {
    }

    public function update(string $sellerPartnerId, array $orders): void
    {
        if (empty($orders))
            return;

        usort($orders, fn($order) => $order['LastUpdateDate']);
        $lastUpdate = $this->getLastUpdate($orders);
        if (!$this->getOrderState($sellerPartnerId)) {
            $this->initiateState($sellerPartnerId, $orders, $lastUpdate);
            return;
        }

        $this->updateState($sellerPartnerId, $orders, $lastUpdate);
        $this->registerEvents($sellerPartnerId);
    }

    protected function getOrderState(string $sellerPartnerId): bool
    {
        $state = $this->orderRepository->getBySellerId($sellerPartnerId);
        if (!$state)
            return false;

        $this->hashedOrders = array_reduce($state->getOrders(), function ($list, $order) {
            $list[$order['payload']['AmazonOrderId']] = $order['hash'];
            return $list;
        }, []);

        return true;
    }

    protected function initiateState(string $sellerPartnerId, array $orders, string $lastUpdate): void
    {
        $this->orderRepository->insert(
            $sellerPartnerId,
            array_reduce($orders, function($list, $order) {
                $list[] = PayloadHashed::withHash($order);
                return $list;
            }, []),
            $lastUpdate
        );
    }

    public function getLastUpdate($orders): string|null
    {
        if (empty($orders))
            return null;

        return $orders[count($orders) - 1]['LastUpdateDate'];
    }

    protected function updateState(string $sellerPartnerId, array $orders, string|null $lastUpdate): void
    {
        foreach ($orders as $order) {
            if (!isset($this->hashedOrders[$order['AmazonOrderId']])) {
                $this->createOrder($sellerPartnerId, $order);
                continue;
            }

            if ($this->hashedOrders[$order['AmazonOrderId']] !== PayloadHashed::hash($order)) {
                $this->updateOrder($sellerPartnerId, $order);
            }
        }

        $this->updateStateDate($sellerPartnerId, $lastUpdate);
    }

    protected function createOrder(string $sellerPartnerId, array $order): void
    {
        ['hash' => $hash, 'payload' => $payload] = PayloadHashed::withHash($order);
        $this->orderRepository->addOrder($sellerPartnerId, $payload, $hash);
        $this->pushCreatedOrder($order);
    }

    protected function updateOrder(string $sellerPartnerId, array $order): void
    {
        ['hash' => $hash, 'payload' => $payload] = PayloadHashed::withHash($order);
        $this->orderRepository->updateOrder($sellerPartnerId, $payload, $hash);
        $this->pushUpdatedOrder($order);
    }

    protected function updateStateDate(string $sellerPartnerId, string|null $lastUpdate): void
    {
        if (empty($this->createdOrders) && empty($this->updatedOrders))
            return;

        $this->orderRepository->updateStateDate($sellerPartnerId, $lastUpdate);
    }

    protected function pushCreatedOrder(array $order): void
    {
        $this->createdOrders[] = $order;
    }

    protected function pushUpdatedOrder(array $order): void
    {
        $this->updatedOrders[] = $order;
    }

    protected function registerEvents(string $sellerPartnerId): void
    {
        foreach ($this->createdOrders as $order) {
            $this->eventRepository->addEvent($sellerPartnerId, 'order_created', $order);
        }

        foreach ($this->updatedOrders as $order) {
            $this->eventRepository->addEvent($sellerPartnerId, 'order_updated', $order);
        }
    }
}
