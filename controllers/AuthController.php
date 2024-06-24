<?php

/**
 * The Auth class handles authentication and user management.
 *
 * GraphenePHP Auth Controller
 *
 * This class provides validation functionalities for form fields.
 * It allows defining validation rules and callbacks for each field,
 * and returns error messages for invalid fields.
 *
 * @package GraphenePHP
 * @version 2.0.0
 */
class Auth
{
    protected $db;
    protected $loginId;
    protected $currentLog;
    protected $userID;
    protected $email;
    protected $gender;
    protected $ip;
    protected $os;
    protected $browser;
    protected $name;
    protected $phone;
    protected $orderId;
    protected $role;
    protected $status;
    protected $error;
    protected $errorMsgs;
    protected $errors;

     /**
     * Auth constructor.
     */
    public function __construct()
    {
        $this->errors = "";
    }



     // get user by phone

      public static function getUserByPhone($phone)
    {   
        DB::connect();
        $getUser = DB::select('users', '*', "phone = '$phone' and status <> 'deleted'")->fetch();
        DB::close();
        if ($getUser)
            return $getUser;
        else
            return ['error' => true, "errorMsgs" => ['user' => "User Not Found"]];
    }
    

     /**
     * Checks the validity of the session.
     *
     * @return array|false The current session information if valid, or false otherwise.
     */
    // Check the session validity
    public function checkSession()
    {
        $this->loginId = $_COOKIE['auth'];

        DB::connect();
        $query = DB::select('logs', '*', "loginId = '$this->loginId' AND loggedoutAt is null")->fetchAll();
        DB::close();

        if ($query) {
            $this->currentLog = $query[0];
            return $this->currentLog;
        } else {
            return false;
        }
    }


    // login By OTP

     public function login($phone)
    {
        DB::connect();
        $this->phone = $phone;
        $this->userID = $this->getUserByPhone($phone)['userID'];
        DB::close();

    
        DB::connect();
        $loginQuery = DB::select('users', '*', "phone = '$this->phone' and status <> 'deleted'")->fetchAll()[0];
        DB::close();
        
        // Check if user exists
        if ($loginQuery) {
                $this->loginId = md5(sha1($this->phone). sha1(time()));
                

                $this->ip = getDevice()['ip'];
                $this->os = getDevice()['os'];
                $this->browser = getDevice()['browser'];

                $time = date_create()->format('Y-m-d H:i:s');
                $data = [
                    'loginId' => $this->loginId,
                    'userID' => $this->userID,
                    'ip' => $this->ip,
                    'browser' => $this->browser,
                    'os' => $this->os,
                    'loggedinat' => $time
                ];

                DB::connect();
                $insertedLog = DB::insert('logs', $data);
                DB::close();

                if ($insertedLog) {
                    setcookie("auth", $this->loginId, time() + (86400 * 365), "/");

                    DB::connect();
                        $checkData = DB::select('users', '*', "phone = '$this->phone' and name IS NULL OR email IS NULL OR gender IS NULL")->fetchAll()[0];

                    DB::close();

                    if ($checkData['name']=='' OR $checkData['email']=='' OR $checkData['gender']=='') {
                        header("Location:" . route('')."user/profile");
                    } else {
                        header("Location:" . route(''));
                    }
                    
                } else $this->errors = "Internal Server Error";
                
            
        } else $this->errors = "User Not Found ";
            
            return ['error' => true, 'errorMsg' => $this->errors];
    }


    public function checkPhone($phone, $role)
    {
        DB::connect();
        $result = DB::select('users', '*', "phone = '$phone' and role = '$role' and status <> 'deleted'")->fetchAll();
        DB::close();
        return count($result);
    }
    

    /**
     * Get a  user with userID.
     *
     * @param string $userID The userID of the user.
     * @return array The result of the select query.
     */
    public static function getUser($userID)
    {   
        DB::connect();
        $userID = DB::sanitize($userID);
        $getUser = DB::select('users', '*', "userID = '$userID' and status <> 'deleted'")->fetch();
        DB::close();
        if ($getUser)
            return $getUser;
        else
            return ['error' => true, "errorMsgs" => ['user' => "User Not Found"]];
    }


    /**
     * Get all users.
     * @return array The result of the select query.
     */
    public static function getUsers()
    {
        DB::connect();
        $users = DB::select('users', '*', "status <> 'deleted'")->fetchAll();
        DB::close();
        if ($users)
            return $users;
        else
        return ['error' => true];
    }


    

    // register using OTP

    public function register($phone, $orderId, $role, $status = "pending")
    {
        // Sanitize fields
        DB::connect();
        $this->phone = trim(DB::sanitize($phone));
        $this->orderId = trim(DB::sanitize($orderId));
        $this->role = trim(DB::sanitize($role));
        $this->status = trim(DB::sanitize($status));
        DB::close();

        $this->userID = md5(md5($this->phone.$this->orderId).md5(time().uniqid()));

        // fields array
        $fields = [
            'phone' => [
                'value' => $this->phone,
                'rules' => [
                    [
                        'type' => 'required',
                        'message' => "Phone can't be empty",
                    ],
                    [
                        'type' => 'phone',
                        'message' => "Invalid Phone",
                    ],
                    [
                        'type' => 'custom',
                        'message' => 'Phone already in use',
                        'validate' => function () {
                            return !($this->checkPhone($this->phone, $this->role));
                        },
                    ],
                ]
            ],
            
        ];

        // Call the Validator::validate function
        $validate = Validator::validate($fields);
        if ($validate['error']) {
            return ['error' => $validate['error'], 'errorMsgs' => $validate['errorMsgs']];
        } else {

            $data = array(
                'userID' => $this->userID,
                'orderId' => $this->orderId,
                'phone' => $this->phone,
                'role' => $this->role,
                'status' => $this->status,
                'createdAt' => date('Y-m-d H:i:s'),
            );


            DB::connect();
            $createUser = DB::insert('users', $data);
            DB::close();

            if ($createUser) {
                $this->error = false;
                $this->errorMsgs['createUser'] = '';
            } else {
                $this->error = true;
                $this->errorMsgs['createUser'] = 'User account creation failed';
            }

            if ($this->error) {
                return ['error' => $this->error, 'errorMsgs' => $this->errorMsgs];
            } else {
                return ['error' => $this->error, 'errorMsgs' => $this->errorMsgs, 'message' => 'Registration successful'];
            }
        }

    }

    // Send OTP
    public function sendOTP($phone){

        $getUser=$this->getUserByPhone($phone);

        controller('OTPLess');
        $sms = new OTPLess();
        $message = $sms->sendOTP("+91".$phone);
        $result = json_decode($message, true);

        if($getUser['phone'] && $result['orderId']){

          $userID=$getUser['userID'];  

          $updateData = [
              'phone' => $phone,
              'orderId'=>$result['orderId']
          ];


          DB::connect();
          $updateOTP = DB::update('users', $updateData, "userID = '$userID'");
          DB::close();

          if($updateOTP){
            $this->error = false;
            $this->errorMsgs['sendOTP'] = '';
          }
          else{
             $this->error = true;
             $this->errorMsgs['sendOTP'] = 'Unable to send OTP';
          }
      }else{
        $register = $this->register($phone,$result['orderId'],'user');   
          if($register){
            $this->error = false;
            $this->errorMsgs['sendOTP'] = '';
            $this->errorMsgs['orderId'] = $result['orderId'];
          }
          else{
             $this->error = true;
             $this->errorMsgs['sendOTP'] = 'Unable to send OTP';
          }
      }

           if ($this->error) {
                return ['error' => $this->error, 'errorMsgs' => $this->errorMsgs];
           } else {
                return ['error' => $this->error, 'errorMsgs' => $this->errorMsgs, 'message' => 'OTP has been sent!'];
            }
    }



// verify OTP
public function verifyOTP($phone, $otp)
{
    DB::connect();
    $this->userID=$this->getUserByPhone($phone)['userID'];
    $this->phone = $phone;
    $this->status ='verified';
    DB::close();

    DB::connect();
    $checkOTP = DB::select('users', '*', "phone = '$phone' and status <> 'deleted' ")->fetch();
    DB::close();


    // Define validation rules for fields
    $fields = [
        'userId' => [
            'values'=> $this->userID,
            'rules' => [
                [
                    'type' => 'custom',
                    'message' => 'Invalid User',
                    'validate' => function () {
                        return $this->getUser($this->userID);
                    },

                ]
            ]
        ],
         'phone' => [
                'value' => $this->phone,
                'rules' => [
                    [
                        'type' => 'required',
                        'message' => "Phone can't be empty",
                    ],
                    [
                        'type' => 'phone',
                        'message' => "Invalid Phone",
                    ],
                    [
                        'type' => 'custom',
                        'message' => 'Phone already in use',
                        'validate' => function () {
                            return !($this->checkPhone($this->phone, $this->role));
                        },
                    ],
                ]
            ],
    ];

    // Call the validateFields function
    $validate = Validator::validate($fields);

    if ($validate['error']) {
        return ['error' => $validate['error'], 'errorMsgs' => $validate['errorMsgs']];
    } else {

        $updateData = [
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'otp'=>$this->orderId,
            'verifiedAt' => date('Y-m-d H:i:s'),
        ];

        controller('OTPLess');
        $sms = new OTPLess();
        $message = $sms->verifyOTP($this->phone,$otp,$checkOTP['otp']);

        if ($checkOTP){
            
             DB::connect();
            $updateUser = DB::update('users', $updateData, "userID = '$this->userID'");
            DB::close();

            if ($updateUser) {
                return $updateData;
            } else {
                return [
                    'error' => true,
                    'errorMsgs' => ['updateUser' => 'Verification Failed!'],
                ];
            }
        }
        else{
            return [
                    'error' => true,
                    'errorMsgs' => ['updateUser' => 'Verification Failed!'],
                ];
        }
        
    }
}



        /**
     * Edits user information.
     *
     * @param array $data The user data to be edited.
     * @return array The result of the edit operation.
     */public function edit($userID, $data)
{
    DB::connect();
    $this->name = trim(DB::sanitize($data['name']));
    $this->gender = trim(DB::sanitize($data['gender']));
    $this->userID = trim(DB::sanitize($userID));
    $this->email = trim(DB::sanitize($data['email']));
    $this->phone = trim(DB::sanitize($data['phone']));
    // $this->password = DB::sanitize($data['password']);
    // $this->role = trim(DB::sanitize($data['role']));
    // $this->status = trim(DB::sanitize($data['status']));
    DB::close();

    // Define validation rules for fields
    $fields = [
        'userId' => [
            'values'=> $this->userID,
            'rules' => [
                [
                    'type' => 'custom',
                    'message' => 'Invalid User',
                    'validate' => function () {
                        return $this->getUser($this->userID);
                    },

                ]
            ]
        ],
        'name' => [
            'value' => $this->name,
            'rules' => [
                [
                    'type' => 'required',
                    'message' => "Name can't be empty",
                ],
                [
                    'type' => 'minLength',
                    'message' => "Name can't be less than 6 characters",
                    'minLength' => 6,
                ]
            ]
        ],
        'email' => [
            'value' => $this->email,
            'rules' => [
                [
                    'type' => 'required',
                    'message' => "Email can't be empty",
                ],
                [
                    'type' => 'email',
                    'message' => 'Email is invalid',
                ],
                [
                    'type' => 'custom',
                    'message' => 'Email already in use',
                    'validate' => function () {
                        return true;
                    },
                ],
            ],
        ],
        'phone' => [
            'value' => $this->phone,
            'rules' => [
                [
                    'type' => 'required',
                    'message' => "Phone can't be empty",
                ],
                [
                    'type' => 'phone',
                    'message' => "Invalid Phone",
                ],
                [
                    'type' => 'custom',
                    'message' => 'Phone already in use',
                    'validate' => function () {
                        return true;
                    },
                ],
            ]
        ],
    ];

    // Call the validateFields function
    $validate = Validator::validate($fields);

    if ($validate['error']) {
        return ['error' => $validate['error'], 'errorMsgs' => $validate['errorMsgs']];
    } else {

        $updateData = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            // 'status' => $this->status,
            'verifiedAt' => date('Y-m-d H:i:s'),
        ];
        

        DB::connect();
        $updateUser = DB::update('users', $updateData, "userID = '$this->userID'");
        DB::close();

        if ($updateUser) {
            return $updateData;
        } else {
            return [
                'error' => true,
                'errorMsgs' => ['updateUser' => 'User account Updation failed'],
            ];
        }
    }
}

    /**
     * Deletes a user account.
     *
     * @param string $email The email of the user to be deleted.
     * @return array The result of the delete operation.
     */
    public function delete($userID)
    {
        $check = $this->getUser($userID);
    
        if ($check['error']) {
            return $check;
        }
    
        $data = [
            'status' => 'deleted'
        ];
    
        // Update the 'users' table
        DB::connect();
        $deleteUser = DB::update('users', $data, "userID = '$userID'");
        DB::close();
    
        if (!$deleteUser) {
            return [
                'error' => true,
                'errorMsg' => 'Failed to delete user'
            ];
        }
        DB::connect();
        // Update the 'logs' table
        $updateLogs = DB::update('logs', ['loggedOutAt' => date('Y-m-d H:i:s')], "userID = '$userID'");
    
        DB::close();
    
        if ($updateLogs) {
            return [
                'error' => false,
                'errorMsg' => '',
                'message' => "$userID successfully deleted"
            ];
        } else {
            return [
                'error' => true,
                'errorMsg' => 'Failed to update logs table'
            ];
        }
    }
    

    /**
     * Logs out the user.
     */
    public function logout()
    {
        errors();
        $loginId = App::getSession()['loginId'];
        $time = date_create()->format('Y-m-d H:i:s');

        $data = array(
            'loggedoutAt' => $time
        );

        DB::connect();
        $updateLog = DB::update('logs', $data, "loginId = '$loginId'");
        DB::close();

        if ($updateLog) {
            setcookie("auth", "", time() - (60 * 60 * 24 * 7), "/");
            unset($_COOKIE["auth"]);
            header("Location:" . home() . "login?loggedout=true");
        }
    }


}