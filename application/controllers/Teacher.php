<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
Class Teacher extends CI_Controller{
	private $teacher_id = -1;
	private $data = array();
	function __construct(){
		parent::__construct();
		$this->load->library('session');
		$this->load->helper('xss_helper');
		$this->load->helper('messages');
		$this->load->helper('cookie');
		if($this->auth->check_auth( array(2) ) !== true){
			exit('Yetkiniz Yok!');
		}
		$this->teacher_id = $this->session->userdata('user')->user_id;
		$this->load->model('messages_model');
		$this->load->model('teacher_model');
		//$data = array();
		$this->data['msg_count'] = $this->messages_model->GetUnreadedMsgCount($this->teacher_id);
	}

	public function home($page = 1){
		$data =& $this->data;
		$this->load->model('notice_model');
		$result = $this->notice_model->GetNotices($page);
		$data['notices'] = $result['limited'];
		$data['all_count'] = $result['all_count'];

		$data['teacher_count'] = $this->teacher_model->GetTeacherCount();

		$this->load->model('student_model');
		$data['student_count'] = $this->student_model->GetStudentCount();

		$this->load->model('course_model');
		$data['course_count'] = $this->course_model->GetCourseCount();

		$this->load->model('departments_model');
		$data['department_count'] = $this->departments_model->GetDepartmentCount();

		$config['base_url'] = site_url() . '/admin/home';
		$config['total_rows'] = $data['all_count'];
		$config['per_page'] = 5;
		$this->load->library('defaultpagination');
		$data['pagination'] = $this->defaultpagination->create_links($config);


		$this->load->view('teacher/home', $data);
	}

	public function weekly_programs($page = 1){
		$data =& $this->data;
		$this->load->model('course_model');
		$semester = $this->course_model->GetCurrentSemester();
		if($semester != null){
			$this->load->model('departments_model');
			$data['departments'] = $this->departments_model->GetDepartments();

			$program = $this->teacher_model->GetWeeklyProgram($this->session->userdata('user')->user_id);
			//exit(var_dump($program));
			$data['program'] = ($program === null ) ? array() : $program;

			if($this->input->post('days') != null && is_array($this->input->post('days'))){	//program belirlenmis
				$days = $this->input->post('days');
				for($i=0; $i<count($days); $i++){
					for($j=0;$j<8; $j++){
						if($days[$i][$j] == '-1')
							$days[$i][$j] = 0;
					}
				}
				$result = $this->teacher_model->UpdateWeeklyProgram(
					$this->session->userdata('user')->user_id,
					$days
					);
				if($result === true)
					set_success_msg('Program güncellendi.');
				else
					set_error_msg('Program güncellenirken hata!');
			}


			$data['assigned_courses'] = $this->teacher_model->GetAssignedCourses($this->teacher_id, $semester->semester_id);

		}else{
			set_error_msg('Henüz ders dönemi doluşturulmamış!');
		}

		

		//exit(var_dump($data['assigned_courses']));
		

		$this->load->view('teacher/weekly_programs', $data);
	}

	public function attendance(){
		$data =& $this->data;
		$data['program'] = $this->teacher_model->GetWeeklyProgram($this->session->userdata('user')->user_id);
		if($data['program'] != null && !empty($data['program'])){

			$this->load->model('course_model');
			$semester = $this->course_model->GetCurrentSemester();
			if($semester != null){

				$data['assigned_courses'] = $this->teacher_model->GetAssignedCourses(
					$this->session->userdata('user')->user_id,
					$semester->semester_id,
					true //gruplama icin parametre
				);

				//exit(var_dump($data['assigned_courses'] ));

				$course_start = date('Y-m-d', strtotime($semester->courses_start_date));
				$course_end = date('Y-m-d', strtotime($semester->courses_end_date));
				$calendar = array();
				//exit(var_dump($data['program']));

				//{date: yyyy-mm-dd, badge: boolean, title: string, body: string: footer: string, classname: string}
				$course = $this->input->post('selected_course');
				if($course != null){
					$selected_course = new stdClass();
					$temp = explode('-', $course);
					$selected_course->course = $temp[0];
					$selected_course->subclass = $temp[1];
					$selected_course->acd_id = $temp[2];
					$data['selected_course'] = $selected_course;
				}

				foreach ($data['program'] as $prog) {
					if($course != null){
						//exit(var_dump($data['program'] ));
						if($selected_course->course != $prog->course || $selected_course->subclass != $prog->subclass)
							continue;

						$day_space = $prog->day;
						$_date = date('Y-m-d', strtotime($course_start . ' + ' . $day_space . ' days'));
						while($_date <= $course_end){
							$calendar_day = array(
								'date' => $_date,
								/*'badge' => 'true',*/
								/*'title' => 'Ders Var!',*/
								'classname' => 'calendar-day'
							);
							array_push($calendar, $calendar_day);
							$_date = date('Y-m-d', strtotime($_date . ' + 7 days'));
						}

					}
					
				}
				//exit(var_dump($calendar));
				$data['calendar'] = $calendar;

				if($this->input->post('selected_date') != null){
					$selected_date = $this->input->post('selected_date');
					$day = date('w', strtotime($selected_date));
					//gun bilgisi veritabanindaki formata gore duzenleniyor...
					//0: pazartesi
					if($day == 0)
						$day = 7;
					$day--;

					$final_assigned_course = $this->input->post('final_assigned_course');
					$final_subclass = $this->input->post('final_subclass');
					$final_course = $this->input->post('final_course');

					$subclass_list = $this->course_model->GetSubclassByDay(
						$day,
						$this->session->userdata('user')->user_id,
						$final_course,
						$final_subclass
					);

					$data['subclass_list'] = $subclass_list;
					$data['final_date'] = $selected_date;
					$data['final_assigned_course'] = $final_assigned_course;

					//exit(var_dump($subclass_list));
				}else if($this->input->post('final_hour_acd')){
					$temp = explode("-", $this->input->post('final_hour_acd'));
					$final_hour = $temp[0];
					$final_acd = $temp[1];
					$final_date = $this->input->post('final_date');
					$final_day = $this->input->post('final_day');

					$true_hours = array(
						'09:00', '10:00', '11:00', '12:00', 
						'13:00', '14:00', '15:00', '16:00'
					);

					$final_hour = $true_hours[$final_hour];

					$attendance_data = new stdClass();
					$attendance_data->acd_id = $final_acd;
					$attendance_data->date = $final_date;
					$attendance_data->day = $final_day;
					$attendance_data->hour = $final_hour;
					$data['att_data'] = $attendance_data;
					//exit(var_dump($attendance_data));

					$current_attendance = $this->teacher_model->GetAttendance(
						$attendance_data->date,
						$attendance_data->hour,
						$attendance_data->acd_id
					);
					$data['current_attendance'] = $current_attendance;
					$data['ready_to_att'] = true;

					$this->load->model('student_model');
					$class_list =  $this->student_model->GetEnrolmentsByAssignedCourseData($attendance_data->acd_id);
					$data['enrolments'] = ($class_list != null) ? $class_list : array();

					//$data['attendance_data'] = $attendance_data;


				}else if($this->input->post('finish_att') != null){
					$checked_users = $this->input->post('att_check');
					$date = $this->input->post('date');
					$acd_id = $this->input->post('acd_id');
					$hour = $this->input->post('hour');
					$this->load->model('student_model');
					$class_list =  $this->student_model->GetEnrolmentsByAssignedCourseData($acd_id);
					$att_array = array();
					foreach ($class_list as $student) {
						$att_row = array();
						$att_row['student_id'] = $student->user_id;
						$att_row['date'] = $date;
						$att_row['hour'] = $hour;
						$att_row['state'] = '0';
						$att_row['assigned_course_data'] = $acd_id;
						if($checked_users != null && is_array($checked_users)){
							foreach ($checked_users as $usr) {
								if($usr == $student->user_id){
									$att_row['state'] = '1';
									break 1;
								}
							}
						}
						array_push($att_array, $att_row);
					}
					//exit(var_dump($att_array));
					$isSuccess = $this->teacher_model->UpdateAttendanceFromArray($att_array);

					if($isSuccess === true){
						$data['att_finished'] = '1';
						set_success_msg('<script>$("body").html("<div class=\'alert alert-success text-center\'><h3>Yoklama kaydı başarılı.</h3><br><button onclick=\'window.close();\' class=\'btn btn-default\'>Kapat</button></div>");</script>');
					}else{
						$data['att_finished'] = '1';
						set_error_msg('<script>$("body").html("<div class=\'alert alert-danger text-center\'><h3>Yoklama kaydedilirken beklenmeyen hata.</h3><br><button onclick=\'window.close();\' class=\'btn btn-default\'>Kapat</button></div>");</script>');
					}
				}
			}else{
				set_error_msg('Henüz aktif bir dönemde değilsiniz!');
			}

		}else{
			set_error_msg('Henüz bir programınız yok!');
		}

		


		$this->load->view('teacher/attendance', $data);
	}

	public function attendance_state(){
		$data =& $this->data;
		$this->load->model('course_model');
		$semester = $this->course_model->GetCurrentSemester();
		if($semester != null){
			$data['assigned_courses'] = $this->teacher_model->GetAssignedCourses(
				$this->session->userdata('user')->user_id,
				$semester->semester_id,
				true //gruplama icin parametre
			);
			if($this->input->post('selected_course') != null && $this->input->post('inspect_att') == null){
				$assigned_course = $this->input->post('selected_course');
				$att_result = $this->teacher_model->GetAttendanceList(
					$assigned_course,
					$this->session->userdata('user')->user_id
				);
				if($att_result != null){
					$data['att_result'] = $att_result;
					$data['selected_course'] = $assigned_course;
				}
				else{
					set_error_msg('Bu şubeye ait yoklama bulunamadı.');
				}
			}else if($this->input->post('inspect_att') != null){
				$student_id = $this->input->post('inspect_att');
				$selected_course = $this->input->post('selected_course');
				$user_att = $this->teacher_model->GetUserAttendance($student_id, $selected_course);
				if($user_att !== null){
					$data['user_att'] = $user_att;
				}else{
					set_error_msg('Yoklama getirilirken beklenmeyen hata!');
				}
			}
		}else{
			set_error_msg('Henüz aktif bir dönemde değilsiniz!');
		}
		$this->load->view('teacher/attendance_state', $data);
	}

	public function add_exam($param = 0){
		$data =& $this->data;
		$this->load->model('course_model');
		$semester = $this->course_model->GetCurrentSemester();
		if($semester != null){
			$data['assigned_courses'] = $this->teacher_model->GetAssignedCourses(
				$this->session->userdata('user')->user_id,
				$semester->semester_id,
				true //gruplama icin parametre
			);

			if($this->input->post('create_new_exam') != null){
				$data['new_exam'] = true;
				$this->load->model('student_model');
				$assign_id = $this->input->post('assign_id');
				$subclass = $this->input->post('subclass');
				$data['assign_id'] = $assign_id;
				$class_list =  $this->student_model->GetEnrolmentsByAssignedCourse($assign_id);
				if($class_list != null){
					$data['class_list'] = $class_list;
				}else{
					set_error_msg('Bu sınıfa henüz öğrenci kayıt olmamış.');
				}

			}else if($this->input->post('save_exam') != null){
				//exit(var_dump($_POST));
				$exam_id = $this->input->post('exam_id');
				if($exam_id == null){
					$isSuccess = $this->teacher_model->SaveExam(
						$this->input->post('assign_id'),
						$this->input->post('new_exam_name'),
						$this->input->post('results')
					);
				}else{
					$isSuccess = $this->teacher_model->SaveExam(
						$this->input->post('assign_id'),
						$this->input->post('new_exam_name'),
						$this->input->post('results'),
						$exam_id
					);
				}
				
				//exit(var_dump($isSuccess));
				if($isSuccess === true){
					set_success_msg('Sınav sonuçları kaydedildi.');
				}else{
					set_error_msg('Sınav sonucu kaydedilirken beklenmeyen hata!');
				}
			}else if($this->input->post('edit_prev_exam') != null){
				$exam_id = $this->input->post('selected_exam');
				$data['edit_prev_exam'] = true;
				$this->load->model('student_model');
				$assign_id = $this->input->post('assign_id');
				$subclass = $this->input->post('subclass');
				$data['assign_id'] = $assign_id;
				$data['exam_id'] = $exam_id;
				//$class_list =  $this->student_model->GetEnrolmentsByAssignedCourse($assign_id);
				$exam = $this->teacher_model->GetResults($exam_id);
				if($exam != null){
					$data['exam_list'] = $exam;
				}else{
					set_error_msg('Böyle bir sınav yok!');
				}
			}else if($this->input->post('selected_course') != null){
				$temp = explode('-', $this->input->post('selected_course'));
				if(count($temp) == 3){
					$assign_id = $temp[2];
					$subclass = $temp[1];
					$exam_list = $this->teacher_model->GetExams($assign_id);
					$exam_list = ($exam_list == null) ? array() : $exam_list;
					$data['exam_list'] = $exam_list;
					$data['assign_id'] = $assign_id;
					$data['subclass'] = $subclass;
				}	
			}
		

		}else{
			set_error_msg('Henüz aktif bir dönemde değilsiniz!');
		}


		$this->load->view('teacher/add_exam', $data);
	}

	public function messages(){
		$data =& $this->data;
		$flash = $this->session->flashdata('data');
		if($flash != null){
			$data = array_merge($data, $flash);
			//exit(var_dump($flash));
		}

		$this->load->model('messages_model');
		$data['msg_list'] = $this->messages_model->LoadLastMessagesList($this->session->userdata('user')->user_id);
		$this->load->model('student_model');
		$data['student_list'] = $this->student_model->GetAllStudents();

		if($this->input->post('start_chat') != null || (isset($data['chat_target']) && $data['chat_target'] != null) ){
			$sender = (!isset($data['chat_target'])) ? $this->input->post('start_chat') : $data['chat_target'];
			$last100 = $this->messages_model->GetMesagesBySender($sender, $this->session->userdata('user')->user_id);
			//exit(var_dump($last100));
			$data['last100'] = $last100;
			$data['chat_target'] = $sender;

			if($this->input->post('start_chat') != null){
				$this->session->set_flashdata('data', $data);
				redirect('teacher/messages');
			}
			

		}else if($this->input->post('message_content') != null){
			$content = $this->input->post('message_content');
			$target = $this->input->post('msg-target');
			$sender = $this->session->userdata('user')->user_id;

			$result = $this->messages_model->SendMessage($sender, $target, $content);
			if($result === false)
				set_error_msg('Beklenmeyen hata!');

			$sender = $target;
			$last100 = $this->messages_model->GetMesagesBySender($sender, $this->session->userdata('user')->user_id);
			//exit(var_dump($last100));
			$data['last100'] = $last100;
			$data['chat_target'] = $sender;

			$this->session->set_flashdata('data', $data);
			redirect('teacher/messages');

		}
		$this->load->view('teacher/messages', $data);
	}

	public function settings(){
		$data =& $this->data;
		if($this->input->post('user_name') != null){
			if(xss_check()){
				$this->load->model('user_model');
				$result = $this->user_model->ChangeUserName(
					strip_tags($this->input->post('user_name')),
					$this->user_sess->user_id
					);
				if($result === true)
					set_success_msg('Ad soyad başarıyla değiştirildi.');
				else
					set_error_msg('Beklenmeyen hata!');
			}

		}else if($this->input->post('user_email') != null){
			if(xss_check()){
				$email = $this->input->post('user_email');
				if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$this->load->model('user_model');
					$result = $this->user_model->ChangeEmail(
						strip_tags($email),
						$this->user_sess->user_id
						);
					if($result === true)
						set_success_msg('Email başarıyla değiştirildi.');
					else
						set_error_msg('Beklenmeyen hata!');
				}else{
					set_error_msg('Geçersiz email!');
				}

			}

		}


		$this->load->view('teacher/settings', $data);
	}





}
?>