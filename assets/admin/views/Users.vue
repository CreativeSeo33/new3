<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Пользователи</h1>
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        @click="openCreate = true"
      >
        Создать пользователя
      </button>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left">Имя</th>
            <th class="px-4 py-2 text-left">Роли</th>
            <th class="px-4 py-2 text-left w-28">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-4 py-2">
              <Input v-model="row.nameProxy" placeholder="Имя пользователя" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <div class="flex flex-wrap gap-3">
                <label v-for="role in rolesOptions" :key="role" class="inline-flex items-center gap-2 text-xs">
                  <input type="checkbox" class="h-4 w-4" :value="role" v-model="row.rolesChecked" @change="() => saveRow(row)" />
                  <span>{{ role }}</span>
                </label>
              </div>
              <div class="mt-1 text-[11px] text-neutral-500">ROLE_USER назначается автоматически</div>
            </td>
            <td class="px-4 py-2">
              <button
                type="button"
                class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700"
                @click="confirmDelete(row.id)"
              >
                Удалить
              </button>
            </td>
          </tr>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="3" class="px-4 py-6 text-center text-neutral-500">Нет пользователей</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal create -->
    <DialogRoot v-model:open="openCreate">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
        <DialogContent
          class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900"
        >
          <div class="mb-2">
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новый пользователь</DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Заполните поля ниже</DialogDescription>
          </div>

          <form class="space-y-4" @submit.prevent="createSubmit">
            <Input
              v-model="createForm.name"
              label="Имя"
              placeholder="Иван Иванов"
              :error="nameError"
              @blur="nameTouched = true"
            />
            <Input
              v-model="createForm.password"
              label="Пароль"
              type="password"
              placeholder="минимум 6 символов"
              :error="passwordError"
              @blur="passwordTouched = true"
            />
            <Input
              v-model="createForm.passwordConfirm"
              label="Подтвердите пароль"
              type="password"
              placeholder="повторите пароль"
              :error="passwordConfirmError"
              @blur="confirmTouched = true"
            />
            <div class="text-sm">
              <div class="mb-1 text-neutral-600 dark:text-neutral-300">Роли</div>
              <div class="flex flex-wrap gap-3">
                <label v-for="role in rolesOptions" :key="role" class="inline-flex items-center gap-2 text-xs">
                  <input type="checkbox" class="h-4 w-4" :value="role" v-model="createForm.rolesChecked" />
                  <span>{{ role }}</span>
                </label>
              </div>
              <div class="mt-1 text-[11px] text-neutral-500">ROLE_USER назначается автоматически</div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
              <button
                type="submit"
                :disabled="submitting || !canSubmitCreate"
                class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
              >
                {{ submitting ? 'Сохранение…' : 'Сохранить' }}
              </button>
            </div>
          </form>

          <DialogClose as-child>
            <button aria-label="Закрыть" class="sr-only">Закрыть</button>
          </DialogClose>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2200">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
    <ConfirmDialog v-model="deleteOpen" :title="'Удалить пользователя?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { useCrud } from '@admin/composables/useCrud'
import { UserRepository, type User } from '@admin/repositories/UserRepository'

type EditableRow = {
  id: number
  nameProxy: string
  rolesChecked: string[]
}

const repo = new UserRepository()
const crud = useCrud<User>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)

const rows = ref<EditableRow[]>([])
const rolesOptions = ['ROLE_ADMIN', 'ROLE_MANAGER'] as const

onMounted(async () => {
  await crud.fetchAll({ itemsPerPage: 500 })
  syncRows()
})

watch(
  () => state.items,
  () => syncRows(),
  { deep: true }
)

function syncRows() {
  const items = ((state.items ?? []) as User[]).slice()
  items.sort((a, b) => String((a as any).name || '').localeCompare(String((b as any).name || '')))
  rows.value = items.map((u) => ({
    id: Number(u.id),
    nameProxy: String((u as any).name ?? ''),
    rolesChecked: (u.roles || []).filter((r) => r !== 'ROLE_USER'),
  }))
}

async function saveRow(row: EditableRow) {
  const roles = (row.rolesChecked || []).slice()
  const payload: Partial<User> = { name: row.nameProxy.trim() || null, roles }
  await crud.update(row.id, payload)
  publishToast('Сохранено')
}

// Create form
const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ name: '', password: '', passwordConfirm: '', rolesChecked: [] as string[] })

// validation for password + confirm
const nameTouched = ref(false)
const nameError = computed(() => {
  const n = createForm.name.trim()
  if (!nameTouched.value) return ''
  if (!n) return 'Имя обязательно'
  if (n.length < 2) return 'Минимум 2 символа'
  return ''
})
const passwordTouched = ref(false)
const confirmTouched = ref(false)
const passwordError = computed(() => {
  const pwd = createForm.password.trim()
  if (!passwordTouched.value && !confirmTouched.value) return ''
  if (!pwd) return 'Пароль обязателен'
  if (pwd.length < 6) return 'Минимум 6 символов'
  return ''
})
const passwordConfirmError = computed(() => {
  const confirm = createForm.passwordConfirm.trim()
  if (!confirmTouched.value && !passwordTouched.value) return ''
  if (!confirm) return 'Повторите пароль'
  if (confirm !== createForm.password.trim()) return 'Пароли не совпадают'
  return ''
})
const canSubmitCreate = computed(() => {
  return createForm.name.trim().length >= 2 &&
    !!createForm.password.trim() &&
    !!createForm.passwordConfirm.trim() &&
    createForm.password.trim().length >= 6 &&
    createForm.password.trim() === createForm.passwordConfirm.trim()
})

async function createSubmit() {
  nameTouched.value = true
  passwordTouched.value = true
  confirmTouched.value = true
  if (!canSubmitCreate.value) return
  submitting.value = true
  try {
    const roles = (createForm.rolesChecked || []).slice()
    await crud.create({ name: createForm.name.trim() || null, plainPassword: createForm.password.trim(), roles } as Partial<User>)
    syncRows()
    openCreate.value = false
    Object.assign(createForm, { name: '', password: '', passwordConfirm: '', rolesChecked: [] })
    nameTouched.value = false
    passwordTouched.value = false
    confirmTouched.value = false
    publishToast('Пользователь создан')
  } finally {
    submitting.value = false
  }
}

// toasts
const toastCount = ref(0)
const lastToastMessage = ref('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}

// delete
const deleteOpen = ref(false)
const pendingDeleteId = ref<number | null>(null)
function confirmDelete(id: number) {
  pendingDeleteId.value = id
  deleteOpen.value = true
}
async function performDelete() {
  if (pendingDeleteId.value == null) return
  await crud.remove(pendingDeleteId.value)
  rows.value = rows.value.filter(r => r.id !== pendingDeleteId.value!)
  publishToast('Пользователь удалён')
  pendingDeleteId.value = null
  deleteOpen.value = false
}
</script>


