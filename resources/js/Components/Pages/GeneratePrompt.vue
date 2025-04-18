<template>
    <a-spin :spinning="loading">
        <div class="p-4 bg-white shadow sm:p-8 sm:rounded-lg">
            <div class="max-w-xl">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Tạo kế hoạch học tập</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Tạo kế hoạch học tập của bạn với sự trợ giúp của AI.
                        </p>
                    </header>

                    <div class="mt-6 space-y-6 ">
                        <div v-if="canGenerateNextWeek" class="flex flex-col gap-4 sm:flex-row">
                            <button type="button"
                                class="inline-flex items-center justify-center flex-1 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="generateTasks">
                                Tạo kế hoạch tuần mới
                            </button>

                            <button type="button"
                                class="inline-flex items-center justify-center flex-1 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-green-600 border border-transparent rounded-md hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                @click="generateNextWeek">
                                Tạo kế hoạch tuần tiếp theo
                            </button>
                        </div>

                        <div v-else>
                            <button type="button"
                                class="inline-flex items-center justify-center w-full px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="generateTasks">
                                Tạo kế hoạch tuần mới
                            </button>
                        </div>

                        <div v-if="error" class="p-4 rounded-md bg-red-50">
                            <div class="flex">
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Có lỗi xảy ra</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>{{ error }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="successMessage" class="p-4 rounded-md bg-green-50">
                            <div class="flex">
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">Thành công</h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>{{ successMessage }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </a-spin>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { message } from 'ant-design-vue';
import Swal from 'sweetalert2';

const props = defineProps({
    profileId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['generated']);

const loading = ref(false);
const error = ref('');
const successMessage = ref('');
const canGenerateNextWeek = ref(false);

onMounted(async () => {
    await checkCurrentWeek();
});

const checkCurrentWeek = async () => {
    try {
        // Kiểm tra xem đã có tuần học nào cho profile này chưa
        const response = await axios.get(`/api/learning/tasks/${props.profileId}`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        // Nếu có week data, cho phép tạo tuần tiếp theo
        if (response.data.status === 200 && response.data.data.week) {
            canGenerateNextWeek.value = true;
        } else {
            canGenerateNextWeek.value = false;
        }
    } catch (error) {
        console.error('Error checking current week:', error);
        // Nếu gặp lỗi (ví dụ: chưa có tuần nào), chỉ cho phép tạo tuần mới
        canGenerateNextWeek.value = false;
    }
};

const generateTasks = async () => {
    loading.value = true;
    error.value = '';
    successMessage.value = '';

    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.post(`/api/learning/generate/${props.profileId}`, {}, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        if (response.data.status === 201) {
            successMessage.value = response.data.message || 'Đã tạo kế hoạch học tập thành công!';
            canGenerateNextWeek.value = true;
            emit('generated');
        } else {
            // error.value = response.data.message || 'Có lỗi xảy ra khi tạo kế hoạch học tập.';
            Swal.fire({
                icon: 'error',
                title: 'Có lỗi xảy ra',
                text: response.data.message || 'Có lỗi xảy ra khi tạo kế hoạch học tập.',
            });
        }
    } catch (err) {
        console.error('Error generating tasks:', err);
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Có lỗi xảy ra khi tạo kế hoạch học tập.';
        }
    } finally {
        loading.value = false;
    }
};

const generateNextWeek = async () => {
    loading.value = true;
    error.value = '';
    successMessage.value = '';

    try {
        const response = await axios.post(`/api/learning/generate-next/${props.profileId}`, {}, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        if (response.data.status === 201) {
            // successMessage.value = response.data.message || 'Đã tạo kế hoạch học tập cho tuần tiếp theo!';
            // emit('generated');
            message.success(response.data.message || 'Đã tạo kế hoạch học tập cho tuần tiếp theo!');
        } else {
            // error.value = response.data.message || 'Có lỗi xảy ra khi tạo kế hoạch học tập.';
            message.error(error.value);
        }
    } catch (err) {
        console.error('Error generating next week:', err);
        if (err.response?.data?.message) {
            // error.value = err.response.data.message;
        } else {
            error.value = 'Có lỗi xảy ra khi tạo kế hoạch cho tuần tiếp theo.';
        }
    } finally {
        loading.value = false;
    }
};
</script>