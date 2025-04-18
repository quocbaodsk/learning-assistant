<template>
    <div class="p-4 bg-white shadow sm:p-8 sm:rounded-lg">
        <h2 class="text-lg font-medium text-gray-900">Kế hoạch học tập tuần này</h2>

        <div v-if="loading" class="flex items-center justify-center p-6">
            <div class="inline-block w-8 h-8 border-4 border-indigo-500 rounded-full animate-spin border-t-transparent"></div>
            <p class="ml-3 text-sm text-gray-600">Đang tải kế hoạch học tập...</p>
        </div>

        <div v-else-if="error" class="p-4 mt-4 rounded-md bg-red-50">
            <div class="flex">
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Có lỗi xảy ra</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>{{ error }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="!weekData" class="py-6 text-center">
            <p class="text-gray-500">Chưa có kế hoạch học tập nào. Hãy tạo kế hoạch mới.</p>
        </div>

        <div v-else class="mt-4">
            <!-- Thông tin tuần học -->
            <div class="p-4 mb-6 rounded-lg bg-gray-50">
                <h3 class="font-semibold text-gray-700">Tóm tắt</h3>
                <p class="mt-1 text-sm text-gray-600">{{ weekData.week.summary }}</p>

                <div class="mt-4">
                    <h3 class="font-semibold text-gray-700">Ghi chú</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ weekData.week.notes }}</p>
                </div>

                <div class="flex items-center mt-4 text-sm text-gray-600">
                    <span>Ngày bắt đầu: </span>
                    <span class="ml-1 font-medium">{{ formatDate(weekData.week.start_date) }}</span>
                </div>
            </div>

            <!-- Tabs cho các ngày trong tuần -->
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px space-x-2 overflow-x-auto">
                    <button v-for="day in daysOfWeek" :key="day" @click="activeDay = day" :class="[
                        'py-2 px-3 text-sm font-medium rounded-t-lg',
                        activeDay === day
                            ? 'border-indigo-500 border-b-2 text-indigo-600 bg-indigo-50'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]">
                        {{ day }}
                        <span v-if="getDayTasks(day)?.length" class="ml-1 text-xs">
                            ({{ getCompletedTasksCount(day) }}/{{ getDayTasks(day).length }})
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Tasks của ngày đang active -->
            <div class="mt-4 space-y-4">
                <div v-if="!getDayTasks(activeDay)?.length" class="py-4 text-center">
                    <p class="text-gray-500">Không có nhiệm vụ nào cho ngày {{ activeDay }}.</p>
                </div>

                <div v-for="task in getDayTasks(activeDay)" :key="task.id" class="p-4 transition-colors border rounded-lg hover:bg-gray-50" :class="{ 'border-green-300 bg-green-50': task.is_done }">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 pt-1">
                            <input type="checkbox" :checked="task.is_done" @change="updateTaskStatus(task.id, $event.target.checked)" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                        </div>

                        <div class="flex-grow ml-3">
                            <div class="flex flex-col sm:flex-row sm:justify-between">
                                <h4 class="text-sm font-medium text-gray-900">{{ task.task }}</h4>
                                <span class="mt-1 text-xs text-gray-500 sm:mt-0">{{ task.duration }}</span>
                            </div>

                            <div class="mt-2 space-y-1">
                                <div class="flex items-center text-xs text-gray-500">
                                    <span class="font-medium">Tài nguyên:</span>
                                    <span class="ml-1">{{ task.resource }}</span>
                                </div>

                                <div class="flex items-center text-xs text-gray-500">
                                    <span class="font-medium">Loại:</span>
                                    <span class="ml-1">{{ task.type }}</span>
                                </div>

                                <div class="flex items-center text-xs text-gray-500">
                                    <span class="font-medium">Trọng tâm:</span>
                                    <span class="ml-1">{{ task.focus }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex-shrink-0 ml-2">
                            <button type="button" @click="openEditModal(task)" class="text-gray-400 hover:text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal chỉnh sửa task -->
        <div v-if="editingTask" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500 bg-opacity-75">
            <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">
                <h3 class="text-lg font-medium text-gray-900">Chỉnh sửa nhiệm vụ</h3>

                <form @submit.prevent="updateTaskContent" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nhiệm vụ</label>
                        <input type="text" v-model="editForm.task" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Thời gian</label>
                        <input type="text" v-model="editForm.duration" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tài nguyên</label>
                        <input type="text" v-model="editForm.resource" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Loại</label>
                        <select v-model="editForm.type" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="Video">Video</option>
                            <option value="Article">Bài viết</option>
                            <option value="Exercise">Bài tập</option>
                            <option value="Project">Dự án</option>
                            <option value="Quiz">Quiz</option>
                            <option value="None">Khác</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Trọng tâm</label>
                        <input type="text" v-model="editForm.focus" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required />
                    </div>

                    <div class="flex justify-end pt-4 space-x-3">
                        <button type="button"
                            class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase transition duration-150 ease-in-out border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                            @click="cancelEdit">
                            Hủy
                        </button>

                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                            :disabled="updateLoading">
                            <span v-if="updateLoading" class="inline-block w-4 h-4 mr-2 border-2 border-white rounded-full border-t-transparent animate-spin"></span>
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    profileId: {
        type: Number,
        required: true
    }
});

const loading = ref(true);
const error = ref('');
const weekData = ref(null);
const activeDay = ref('Monday');
const editingTask = ref(null);
const updateLoading = ref(false);

const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

const editForm = ref({
    task: '',
    duration: '',
    resource: '',
    type: '',
    focus: ''
});

onMounted(async () => {
    await fetchTasks();
});

const fetchTasks = async () => {
    loading.value = true;
    error.value = '';

    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.get(`/api/learning/tasks/${props.profileId}`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        // Xử lý response dựa trên cấu trúc API từ Postman
        if (response.data.status === 200) {
            weekData.value = {
                week: response.data.data.week,
                tasks_by_day: response.data.data.tasks_by_day
            };

            // Tìm ngày đầu tiên có task để active
            for (const day of daysOfWeek) {
                if (response.data.data.tasks_by_day[day]?.length) {
                    activeDay.value = day;
                    break;
                }
            }
        } else {
            error.value = response.data.message || 'Không thể tải kế hoạch học tập.';
        }
    } catch (err) {
        console.error('Error fetching tasks:', err);
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Không thể tải kế hoạch học tập.';
        }
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';

    const date = new Date(dateString);
    return new Intl.DateTimeFormat('vi-VN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(date);
};

const getDayTasks = (day) => {
    return weekData.value?.tasks_by_day[day] || [];
};

const getCompletedTasksCount = (day) => {
    const tasks = getDayTasks(day);
    return tasks.filter(task => task.is_done).length;
};

const updateTaskStatus = async (taskId, isDone) => {
    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.patch(`/api/learning/task/${taskId}`,
            { is_done: isDone },
            {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('access_token')}`
                }
            }
        );

        if (response.data.status === 200) {
            // Cập nhật UI
            if (weekData.value && weekData.value.tasks_by_day) {
                for (const day in weekData.value.tasks_by_day) {
                    const taskIndex = weekData.value.tasks_by_day[day].findIndex(t => t.id === taskId);
                    if (taskIndex !== -1) {
                        weekData.value.tasks_by_day[day][taskIndex].is_done = isDone;
                        break;
                    }
                }
            }
        } else {
            console.error('Error updating task status:', response.data.message);
        }
    } catch (error) {
        console.error('Error updating task status:', error);
    }
};

const openEditModal = (task) => {
    editingTask.value = task;
    editForm.value = {
        task: task.task,
        duration: task.duration,
        resource: task.resource,
        type: task.type,
        focus: task.focus
    };
};

const cancelEdit = () => {
    editingTask.value = null;
};

const updateTaskContent = async () => {
    if (!editingTask.value) return;

    updateLoading.value = true;

    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.patch(`/api/learning/task/update/${editingTask.value.id}`,
            editForm.value,
            {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('access_token')}`
                }
            }
        );

        if (response.data.status === 200) {
            // Cập nhật UI với dữ liệu trả về từ API
            const updatedTask = response.data.data;

            if (weekData.value && weekData.value.tasks_by_day) {
                for (const day in weekData.value.tasks_by_day) {
                    const taskIndex = weekData.value.tasks_by_day[day].findIndex(t => t.id === editingTask.value.id);
                    if (taskIndex !== -1) {
                        weekData.value.tasks_by_day[day][taskIndex] = updatedTask;
                        break;
                    }
                }
            }

            editingTask.value = null;
        } else {
            console.error('Error updating task content:', response.data.message);
        }
    } catch (error) {
        console.error('Error updating task content:', error);
    } finally {
        updateLoading.value = false;
    }
};
</script>