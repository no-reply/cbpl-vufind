<?php
/**
 * Database authentication test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Tests\Auth;
use VuFind\Auth\Database;

/**
 * Database authentication test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DatabaseTest extends \VuFind\Tests\DbTestCase
{
    protected $auth;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->auth = $this->getAuthManager()->get('Database');
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        // Set up database adapter:
        static::prepareAdapter();

        // Fail if there are already users in the database (we don't want to run this
        // on a real system -- it's only meant for the continuous integration server)
        $test = new DatabaseTest();
        $userTable = $test->getTable('User');
        if (count($userTable->select()) > 0) {
            throw new \Exception('Test cannot run with pre-existing user data!');
        }
    }

    /**
     * Test account creation is allowed.
     *
     * @return void
     */
    public function testCreateIsAllowed()
    {
        $this->assertTrue($this->auth->supportsCreation());
    }

    /**
     * Support method -- turn an array into a request populated for use by the
     * authentication class.
     *
     * @param array $post Associative array of POST parameters.
     *
     * @return \Zend\Http\Request
     */
    protected function getRequest($post)
    {
        $request = new \Zend\Http\Request();
        $request->setPost(new \Zend\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Support method -- get parameters to create an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Zend\Http\Request
     */
    protected function getAccountCreationRequest($overrides = array())
    {
        $post = $overrides + array(
            'username' => 'testuser', 'email' => 'user@test.com',
            'password' => 'testpass', 'password2' => 'testpass',
            'firstname' => 'Test', 'lastname' => 'User'
        );
        return $this->getRequest($post);
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Zend\Http\Request
     */
    protected function getLoginRequest($overrides = array())
    {
        $post = $overrides + array(
            'username' => 'testuser', 'password' => 'testpass'
        );
        return $this->getRequest($post);
    }

    /**
     * Test blank username.
     *
     * @return void
     */
    public function testCreationWithBlankUsername()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getAccountCreationRequest(array('username' => ''));
        $this->auth->create($request);
    }

    /**
     * Test blank password.
     *
     * @return void
     */
    public function testCreationWithBlankPassword()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getAccountCreationRequest(array('password' => ''));
        $this->auth->create($request);
    }

    /**
     * Test password mismatch.
     *
     * @return void
     */
    public function testCreationWithPasswordMismatch()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getAccountCreationRequest(array('password2' => ''));
        $this->auth->create($request);
    }

    /**
     * Test invalid email.
     *
     * @return void
     */
    public function testCreationWithInvalidEmail()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getAccountCreationRequest(array('email' => 'garbage'));
        $this->auth->create($request);
    }

    /**
     * Test password mismatch.
     *
     * @return void
     */
    public function testSuccessfulCreation()
    {
        $request = $this->getAccountCreationRequest();
        $this->auth->create($request);
    }

    /**
     * Test duplicate username.
     *
     * @return void
     */
    public function testCreationWithDuplicateUsername()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getAccountCreationRequest(array('email' => 'user2@test.com'));
        $this->auth->create($request);
    }

    /**
     * Test duplicate email.
     *
     * @return void
     */
    public function testCreationWithDuplicateEmail()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getAccountCreationRequest(array('username' => 'testuser2'));
        $this->auth->create($request);
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(array('username' => ''));
        $this->auth->authenticate($request);
    }

    /**
     * Test login with blank password.
     *
     * @return void
     */
    public function testLoginWithBlankPassword()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(array('password' => ''));
        $this->auth->authenticate($request);
    }

    /**
     * Test login with unknown username.
     *
     * @return void
     */
    public function testLoginWithUnrecognizedUsername()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(array('username' => 'unknown'));
        $this->auth->authenticate($request);
    }

    /**
     * Test login with bad password.
     *
     * @return void
     */
    public function testLoginWithBadPassword()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(array('password' => "' OR 1=1 LIMIT 1"));
        $this->auth->authenticate($request);
    }

    /**
     * Test successful login.
     *
     * @return void
     */
    public function testLogin()
    {
        $user = $this->auth->authenticate($this->getLoginRequest());
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('user@test.com', $user->email);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        // Delete test user
        $test = new DatabaseTest();
        $userTable = $test->getTable('User');
        $user = $userTable->getByUsername('testuser', false);
        if (empty($user)) {
            throw new \Exception('Problem deleting expected user.');
        }
        $user->delete();
    }
}