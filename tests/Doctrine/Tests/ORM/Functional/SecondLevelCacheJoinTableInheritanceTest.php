<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\ORM\PersistentCollection;
use Doctrine\Tests\Models\Cache\Attraction;
use Doctrine\Tests\Models\Cache\AttractionContactInfo;
use Doctrine\Tests\Models\Cache\AttractionInfo;
use Doctrine\Tests\Models\Cache\AttractionLocationInfo;
use function count;
use function get_class;

/**
 * @group DDC-2183
 */
class SecondLevelCacheJoinTableInheritanceTest extends SecondLevelCacheAbstractTest
{
    public function testUseSameRegion()
    {
        $infoRegion     = $this->cache->getEntityCacheRegion(AttractionInfo::class);
        $contactRegion  = $this->cache->getEntityCacheRegion(AttractionContactInfo::class);
        $locationRegion = $this->cache->getEntityCacheRegion(AttractionLocationInfo::class);

        self::assertEquals($infoRegion->getName(), $contactRegion->getName());
        self::assertEquals($infoRegion->getName(), $locationRegion->getName());
    }

    public function testPutOnPersistJoinTableInheritance()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->loadFixturesAttractionsInfo();

        $this->em->clear();

        self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $this->attractionsInfo[0]->getId()));
        self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $this->attractionsInfo[1]->getId()));
        self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $this->attractionsInfo[2]->getId()));
        self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $this->attractionsInfo[3]->getId()));
    }

    public function testJoinTableCountaisRootClass()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->loadFixturesAttractionsInfo();

        $this->em->clear();

        foreach ($this->attractionsInfo as $info) {
            self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $info->getId()));
            self::assertTrue($this->cache->containsEntity(get_class($info), $info->getId()));
        }
    }

    public function testPutAndLoadJoinTableEntities()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->loadFixturesAttractionsInfo();

        $this->em->clear();

        $this->cache->evictEntityRegion(AttractionInfo::class);

        $entityId1 = $this->attractionsInfo[0]->getId();
        $entityId2 = $this->attractionsInfo[1]->getId();

        self::assertFalse($this->cache->containsEntity(AttractionInfo::class, $entityId1));
        self::assertFalse($this->cache->containsEntity(AttractionInfo::class, $entityId2));
        self::assertFalse($this->cache->containsEntity(AttractionContactInfo::class, $entityId1));
        self::assertFalse($this->cache->containsEntity(AttractionContactInfo::class, $entityId2));

        $queryCount = $this->getCurrentQueryCount();
        $entity1    = $this->em->find(AttractionInfo::class, $entityId1);
        $entity2    = $this->em->find(AttractionInfo::class, $entityId2);

        //load entity and relation whit sub classes
        self::assertEquals($queryCount + 4, $this->getCurrentQueryCount());

        self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $entityId1));
        self::assertTrue($this->cache->containsEntity(AttractionInfo::class, $entityId2));
        self::assertTrue($this->cache->containsEntity(AttractionContactInfo::class, $entityId1));
        self::assertTrue($this->cache->containsEntity(AttractionContactInfo::class, $entityId2));

        self::assertInstanceOf(AttractionInfo::class, $entity1);
        self::assertInstanceOf(AttractionInfo::class, $entity2);
        self::assertInstanceOf(AttractionContactInfo::class, $entity1);
        self::assertInstanceOf(AttractionContactInfo::class, $entity2);

        self::assertEquals($this->attractionsInfo[0]->getId(), $entity1->getId());
        self::assertEquals($this->attractionsInfo[0]->getFone(), $entity1->getFone());

        self::assertEquals($this->attractionsInfo[1]->getId(), $entity2->getId());
        self::assertEquals($this->attractionsInfo[1]->getFone(), $entity2->getFone());

        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();
        $entity3    = $this->em->find(AttractionInfo::class, $entityId1);
        $entity4    = $this->em->find(AttractionInfo::class, $entityId2);

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(AttractionInfo::class, $entity3);
        self::assertInstanceOf(AttractionInfo::class, $entity4);
        self::assertInstanceOf(AttractionContactInfo::class, $entity3);
        self::assertInstanceOf(AttractionContactInfo::class, $entity4);

        self::assertNotSame($entity1, $entity3);
        self::assertEquals($entity1->getId(), $entity3->getId());
        self::assertEquals($entity1->getFone(), $entity3->getFone());

        self::assertNotSame($entity2, $entity4);
        self::assertEquals($entity2->getId(), $entity4->getId());
        self::assertEquals($entity2->getFone(), $entity4->getFone());
    }

    public function testQueryCacheFindAllJoinTableEntities()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->loadFixturesAttractionsInfo();
        $this->evictRegions();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();
        $dql        = 'SELECT i, a FROM Doctrine\Tests\Models\Cache\AttractionInfo i JOIN i.attraction a';
        $result1    = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractionsInfo), $result1);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $this->em->clear();

        $result2 = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractionsInfo), $result2);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        foreach ($result2 as $entity) {
            self::assertInstanceOf(AttractionInfo::class, $entity);
        }
    }

    public function testOneToManyRelationJoinTable()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->loadFixturesAttractionsInfo();
        $this->evictRegions();
        $this->em->clear();

        $entity = $this->em->find(Attraction::class, $this->attractions[0]->getId());

        self::assertInstanceOf(Attraction::class, $entity);
        self::assertInstanceOf(PersistentCollection::class, $entity->getInfos());
        self::assertCount(1, $entity->getInfos());

        $ownerId    = $this->attractions[0]->getId();
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($this->cache->containsEntity(Attraction::class, $ownerId));
        self::assertTrue($this->cache->containsCollection(Attraction::class, 'infos', $ownerId));

        self::assertInstanceOf(AttractionContactInfo::class, $entity->getInfos()->get(0));
        self::assertEquals($this->attractionsInfo[0]->getFone(), $entity->getInfos()->get(0)->getFone());

        $this->em->clear();

        $entity = $this->em->find(Attraction::class, $this->attractions[0]->getId());

        self::assertInstanceOf(Attraction::class, $entity);
        self::assertInstanceOf(PersistentCollection::class, $entity->getInfos());
        self::assertCount(1, $entity->getInfos());

        self::assertInstanceOf(AttractionContactInfo::class, $entity->getInfos()->get(0));
        self::assertEquals($this->attractionsInfo[0]->getFone(), $entity->getInfos()->get(0)->getFone());
    }

    public function testQueryCacheShouldBeEvictedOnTimestampUpdate()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->loadFixturesAttractionsInfo();
        $this->evictRegions();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();
        $dql        = 'SELECT attractionInfo FROM Doctrine\Tests\Models\Cache\AttractionInfo attractionInfo';

        $result1 = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractionsInfo), $result1);
        self::assertEquals($queryCount + 5, $this->getCurrentQueryCount());

        $contact = new AttractionContactInfo(
            '1234-1234',
            $this->em->find(Attraction::class, $this->attractions[5]->getId())
        );

        $this->em->persist($contact);
        $this->em->flush();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();

        $result2 = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractionsInfo) + 1, $result2);
        self::assertEquals($queryCount + 6, $this->getCurrentQueryCount());

        foreach ($result2 as $entity) {
            self::assertInstanceOf(AttractionInfo::class, $entity);
        }
    }
}
