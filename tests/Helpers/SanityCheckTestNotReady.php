<?php

namespace WebPConvert\Tests\Helpers;

use WebPConvert\Helpers\SanityCheck;
use WebPConvert\Exceptions\SanityException;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Helpers\SanityCheck
 * @covers WebPConvert\Helpers\SanityCheck
 */
class SanityCheckTest extends TestCase
{

    /**
     * @covers ::noNUL
     */
    public function testNoNUL()
    {
        $this->expectException(SanityException::class);

        SanityCheck::noNUL("here it comes: \0");
    }

    /**
     * @covers ::noNUL
     */
    public function testNoNUL2()
    {
        $this->expectException(SanityException::class);

        SanityCheck::noNUL("here it comes: " . chr(0));
    }

    /**
     * @covers ::noNUL
     */
    public function testNoNUL3()
    {
        SanityCheck::noNUL("here it does not come");
    }

    /**
     * @covers ::noControlChars
     */
    public function testNoControlChars()
    {
        $this->expectException(SanityException::class);
        $sanitized = SanityCheck::noControlChars("..\1..");
    }

    /**
     * @covers ::noControlChars
     */
    public function testNoControlChars2()
    {
        $this->expectException(SanityException::class);
        $sanitized = SanityCheck::noControlChars("..\n..");
    }

    /**
     * @covers ::noControlChars
     */
    public function testNoControlChars3()
    {
        //$this->expectException(SanityException::class);
        $unsanitized = urldecode("%EF%B8%8F");
        //echo 'look:' . $unsanitized;
        $sanitized = SanityCheck::noControlChars($unsanitized);
        //echo $sanitized;
    }

    /**
     * @covers ::noControlChars
     */
    public function testNoControlChars4()
    {
        //$this->expectException(SanityException::class);
        //SanityCheck::noControlChars("Skærmbillede");
        //SanityCheck::noControlChars("Skrm");
        //SanityCheck::noControlChars("Skærmbillede-2018-10-12-kl.-11.26.38-e1539336533920.png.webp");
        SanityCheck::noControlChars("Skærmbillede-2018-10-12-kl.-11.26.38-e1539336533920.png.webp");
        $sanitized = SanityCheck::noControlChars("space is ok.");
        echo $sanitized;
    }



    /**
     * @covers ::notEmpty
     */
    public function testNotEmpty()
    {
        $this->expectException(SanityException::class);
        SanityCheck::notEmpty(null);
    }

    /**
     * @covers ::notEmpty
     */
     /*
    public function testNotEmpty2()
    {
        $arr = [];
        $this->expectException(SanityException::class);
        SanityCheck::notEmpty($arr['not-exist']);
    }
    */

    /**
     * @covers ::notEmpty
     */
    public function testNotEmpty2()
    {
        SanityCheck::notEmpty('..');
    }

    /**
     * @covers ::noDirectoryTraversal
     */
    public function testNoDirectoryTraversal()
    {
        $this->expectException(SanityException::class);
        SanityCheck::noDirectoryTraversal('hello/../hi');
    }

    /**
     * @covers ::noStreamWrappers
     */
    public function testNoStreamWrappers()
    {
        $this->expectException(SanityException::class);
        SanityCheck::noStreamWrappers('phar://aoeu');
    }

    /**
     * @covers ::noStreamWrappers
     */
    public function testNoStreamWrappers2()
    {
        $this->expectException(SanityException::class);
        SanityCheck::noStreamWrappers("phar:\0//aoeu");
    }

    /**
     * @covers ::mustBeString
     */
    public function testMustBeString()
    {
        $this->expectException(SanityException::class);
        SanityCheck::mustBeString(0);
    }

    /**
     * @covers ::mustBeString
     */
    public function testMustBeString2()
    {
        SanityCheck::mustBeString('');
        SanityCheck::mustBeString('hello');
    }

    /**
     * @covers ::isJSONArray
     */
    public function testIsJSONArray()
    {
        $this->expectException(SanityException::class);
        SanityCheck::isJSONArray('');
    }

    /**
     * @covers ::isJSONArray
     */
    public function testIsJSONArray2()
    {
        SanityCheck::isJSONArray('[]');
        SanityCheck::isJSONArray('["hello", "hi"]');
    }

    /**
     * @covers ::pregMatch
     */
    public function testPregMatch()
    {
        $this->expectException(SanityException::class);
        SanityCheck::pregMatch('#\d#', 'a');
    }

    /**
     * @covers ::pregMatch
     */
    public function testPregMatch2()
    {
        SanityCheck::pregMatch('#\d#', '0');
        SanityCheck::pregMatch('#^[a-z]+$#', 'gd');
    }

    public function testPathBeginsWith()
    {
        SanityCheck::pathBeginsWith('/var/www/my-site/hello.php', '/var/www/');
    }

    public function testPathBeginsWith2()
    {
        $this->expectException(SanityException::class);
        SanityCheck::pathBeginsWith('/var/bin/exec', '/var/www/');
    }

    public function testFindClosestExistingFolderSymLinksExpanded()
    {
        /*
        $this->assertEquals(
            '/var/www/webp-express-tests/we0/plugins-moved/webp-express',
            SanityCheck::findClosestExistingFolderSymLinksExpanded(
                '/var/www/webp-express-tests/we0/plugins-moved/webp-express/i/do/not/exist/test-pattern-tv.jpg'
            )
        );*/

        //echo dirname()
        /*
        echo 'dir:' . SanityCheck::findClosestExistingFolderSymLinksExpanded(
            '/var/www/webp-express-tests/we19/wp-content/webp-express/webp-images/doc-root/wp-content/plugins/webp-express/test/test-pattern-tv.jpg.webp'
        );*/
    }

/*
    public function testPathBeginsWithSymLinksExpanded()
    {
        $this->expectException(SanityException::class);
        SanityCheck::pathBeginsWithSymLinksExpanded(
            '/aoeu/var/www/webp-express-tests/we19/wp-content/webp-express/webp-images/doc-root/wp-content/plugins/webp-express/test/test-pattern-tv.jpg.webp',
            '/var/www/webp-express-tests/'
        );
    }

    public function testPathBeginsWithSymLinksExpanded2()
    {
        SanityCheck::pathBeginsWithSymLinksExpanded(
            '/var/www/webp-express-tests/we19/wp-content/webp-express/webp-images/doc-root/wp-content/plugins/webp-express/test/test-pattern-tv.jpg.webp',
            '/var/www/webp-express-tests/'
        );
    }

    public function testPathBeginsWithSymLinksExpanded3()
    {
        $this->expectException(SanityException::class);
        SanityCheck::pathBeginsWithSymLinksExpanded(
            'aoeu/var/www/webp-express-tests/we19/wp-content/webp-express/webp-images/doc-root/wp-content/plugins/webp-express/test/test-pattern-tv.jpg.webp',
            '/var/www/webp-express-tests/'
        );
    }*/

    public function testAbsPathMicrosoftStyle()
    {
        SanityCheck::absPathMicrosoftStyle("C:\\");
        SanityCheck::absPathMicrosoftStyle("C:/");
    }

    public function testAbsPathMicrosoftStyle2()
    {
        $this->expectException(SanityException::class);
        SanityCheck::absPathMicrosoftStyle("C:1");
    }

    public function testAbsPath()
    {
        SanityCheck::absPath('/var/bin/exec');
        SanityCheck::absPath('var/bin/exec');
    }



}
