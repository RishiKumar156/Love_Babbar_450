
"""Given an array of n distinct elements. Find the minimum number of swaps required to sort the array in strictly increasing order."""
def minSwaps(self, nums):
    #Code here
    mx_n = min_n = nums[0]
    cnt = 0 
    for i in range(len(nums)):
        if min_n > nums[i]:
            min_n = nums[i]
            cnt += 1 
        if mx_n < nums[i]:
            mx_n = nums[i]
            cnt += 1
    return cnt 