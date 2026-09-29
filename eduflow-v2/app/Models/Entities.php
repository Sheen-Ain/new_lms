<?php

namespace App\Models;

use App\Core\Model;

class Course extends Model { protected $table = 'courses'; protected $fillable = ['title', 'description', 'thumbnail', 'status', 'created_by']; }
class Batch extends Model { protected $table = 'batches'; protected $fillable = ['course_id', 'name', 'description', 'start_date', 'end_date', 'max_students', 'status', 'created_by']; }
class Topic extends Model { protected $table = 'topics'; protected $fillable = ['batch_id', 'title', 'description', 'sort_order', 'status', 'created_by']; }
class TopicFile extends Model { protected $table = 'topic_files'; protected $fillable = ['topic_id', 'file_name', 'file_path', 'file_size', 'file_type', 'uploaded_by', 'status']; }
class Assignment extends Model { protected $table = 'assignments'; protected $fillable = ['batch_id', 'topic_id', 'title', 'description', 'total_marks', 'due_date', 'allow_late', 'status', 'created_by']; }
class AssignmentFile extends Model { protected $table = 'assignment_files'; protected $fillable = ['assignment_id', 'file_name', 'file_path', 'file_size', 'file_type', 'status', 'uploaded_by']; }
class Submission extends Model { protected $table = 'submissions'; protected $fillable = ['assignment_id', 'student_id', 'file_name', 'file_path', 'file_size', 'notes', 'marks', 'feedback', 'status', 'is_late', 'submitted_at', 'graded_at', 'graded_by']; }
class Test extends Model { protected $table = 'tests'; protected $fillable = ['title', 'description', 'type', 'batch_id', 'time_minutes', 'status', 'entry_active', 'questions_locked', 'created_by']; }
class TestQuestion extends Model { protected $table = 'test_questions'; protected $fillable = ['question_text', 'is_code', 'created_by']; }
class TestOption extends Model { protected $table = 'test_options'; protected $fillable = ['question_id', 'option_text', 'is_correct', 'sort_order']; }
class TestAttempt extends Model { protected $table = 'test_attempts'; protected $fillable = ['test_id', 'student_id', 'application_id', 'status', 'score', 'percentage', 'total_questions', 'correct_count', 'wrong_count', 'unanswered_count', 'allow_reattempt', 'started_at', 'submitted_at', 'reviewed_at', 'reviewed_by']; }
class Announcement extends Model { protected $table = 'announcements'; protected $fillable = ['batch_id', 'title', 'content', 'priority', 'is_pinned', 'status', 'created_by']; }
class LiveSession extends Model { protected $table = 'live_sessions'; protected $fillable = ['batch_id', 'room_name', 'title', 'started_by', 'status', 'started_at', 'ended_at']; }
class CourseApplication extends Model { protected $table = 'course_applications'; protected $fillable = ['user_id', 'batch_id', 'status', 'rejection_reason', 'applied_at', 'reviewed_at', 'reviewed_by']; }
class FeedbackSession extends Model { protected $table = 'feedback_sessions'; protected $fillable = ['batch_id', 'title', 'description', 'is_active', 'activated_by', 'activated_at', 'deactivated_at']; }
class FeedbackEntry extends Model { protected $table = 'feedback_entries'; protected $fillable = ['session_id', 'batch_id', 'content', 'categories', 'is_reviewed']; }
class User extends Model { protected $table = 'users'; protected $fillable = ['full_name', 'email', 'password', 'gender', 'profile_picture', 'bio', 'phone', 'is_verified', 'status', 'theme_preference', 'current_role', 'bypass_gate', 'cnic', 'user_id_number']; }
