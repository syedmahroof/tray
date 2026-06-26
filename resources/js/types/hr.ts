import type { NamedOption } from '@/types/admin';

export type LeaveType = {
    id: number;
    name: string;
    default_days: string;
    is_paid: boolean;
    color: string | null;
    is_active: boolean;
};

export type LeaveTypeOption = {
    id: number;
    name: string;
    is_paid: boolean;
    default_days: string;
};

export type LeaveBalanceSummary = {
    leave_type_id: number;
    allocated: number;
    used: number;
    remaining: number;
};

export type LeaveAllocationRow = {
    id: number;
    user: NamedOption;
    leave_type: NamedOption & { is_paid: boolean };
    year: number;
    allocated_days: number;
    used_days: number;
    remaining_days: number;
};

export type LeaveStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type LeaveRequest = {
    id: number;
    branch_id: number;
    user_id: number;
    leave_type_id: number;
    start_date: string;
    end_date: string;
    total_days: string;
    reason: string | null;
    status: LeaveStatus;
    approver_id: number | null;
    decision_remarks: string | null;
    decided_at: string | null;
    created_at: string;
};

export type LeaveTypeBadge = NamedOption & {
    is_paid: boolean;
    color: string | null;
};

export type LeaveRequestListItem = LeaveRequest & {
    user: NamedOption;
    leave_type: LeaveTypeBadge;
    approver: NamedOption | null;
};

export type LeaveRequestDetail = LeaveRequestListItem & {
    branch: NamedOption;
};

export type LeaveStatusCount = { status: LeaveStatus; count: number };
